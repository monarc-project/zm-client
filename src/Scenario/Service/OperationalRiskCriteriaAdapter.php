<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Scenario\Service;

use Monarc\Core\Entity\OperationalRiskScaleSuperClass;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Service\OperationalRiskScaleService;

/** Reads the established operational-risk scale service and maps it into a Scenario-only draft input. */
final class OperationalRiskCriteriaAdapter
{
    public function __construct(private OperationalRiskScaleService $operationalRiskScales)
    {
    }

    /** @return array<string, mixed> */
    public function source(Anr $anr): array
    {
        $readAt = gmdate('Y-m-d\\TH:i:s\\Z');
        $scales = $this->operationalRiskScales->getOperationalRiskScales($anr);
        $likelihood = [];
        $dimensions = [];
        foreach ($scales as $scale) {
            if ((int) $scale['type'] === OperationalRiskScaleSuperClass::TYPE_LIKELIHOOD) {
                foreach ($this->orderedComments($scale['comments']) as $comment) {
                    $likelihood[] = $this->level($scale, null, $comment, count($likelihood));
                }
            }
            if ((int) $scale['type'] === OperationalRiskScaleSuperClass::TYPE_IMPACT) {
                foreach ($scale['scaleTypes'] as $type) {
                    if ($type['isHidden']) {
                        continue;
                    }
                    $key = 'operational-scale-type-' . $type['id'];
                    $levels = [];
                    foreach ($this->orderedComments($type['comments']) as $comment) {
                        $levels[] = $this->level($scale, $type, $comment, (int) $comment['scaleValue']);
                    }
                    $dimensions[] = [
                        'key' => $key,
                        'label' => trim((string) $type['label']),
                        'source' => [
                            'scaleId' => (int) $scale['id'],
                            'scaleTypeId' => (int) $type['id'],
                            'label' => trim((string) $type['label']),
                        ],
                        'levels' => $levels,
                    ];
                }
            }
        }

        $guidance = $this->guidance($likelihood, $dimensions);
        $thresholds = $this->thresholds($anr);

        return [
            'sourceAnrId' => (int) $anr->getId(),
            'languageCode' => $anr->getLanguageCode(),
            'readAt' => $readAt,
            'complete' => $guidance === [],
            'guidance' => $guidance,
            'thresholds' => $thresholds,
            'likelihoodLevels' => $likelihood,
            'dimensions' => $dimensions,
        ];
    }

    /** @return array<string, int> */
    public function thresholds(Anr $anr): array
    {
        $low = max(0, (int) $anr->getSeuilRolf1());
        $high = max($low, (int) $anr->getSeuilRolf2());

        return ['low' => max(0, $low), 'high' => $high];
    }

    /** @param array<string, mixed> $source @return array<string, mixed> */
    public function draftInput(array $source): array
    {
        $anrId = (int) $source['sourceAnrId'];

        return [
            'languageCode' => $source['languageCode'],
            'readAt' => $source['readAt'],
            'identifier' => 'operational-risk-anr-' . $anrId . '-' . substr(md5((string) $source['readAt']), 0, 12),
            'title' => 'Operational-risk criteria for Scenario analysis ' . $anrId,
            'aggregationPolicy' => 'highest_consequence_dimension',
            'bands' => self::defaultBands(
                $source['thresholds'] ?? null,
                $source['likelihoodLevels'],
                $source['dimensions']
            ),
            'acceptanceRules' => self::defaultAcceptanceRules(),
            'likelihoodLevels' => $source['likelihoodLevels'],
            'dimensions' => $source['dimensions'],
        ];
    }

    /** @return array<string, mixed> */
    /** @param array<string, int>|null $thresholds */
    public static function defaultBands(
        ?array $thresholds = null,
        array $likelihoodLevels = [],
        array $dimensions = []
    ): array {
        $likelihoodValues = $likelihoodLevels === [] ? [0, 1, 2, 3, 4] : array_column($likelihoodLevels, 'value');
        $impactValues = $dimensions === []
            ? [0, 1, 2, 3, 4]
            : array_column($dimensions[0]['levels'] ?? [], 'value');
        $matrix = [];
        foreach ($likelihoodValues as $likelihood) {
            foreach ($impactValues as $consequence) {
                $matrix[(string) $likelihood][(string) $consequence] = self::approvedBand($likelihood, $consequence);
            }
        }

        return [
            'matrix' => $matrix,
            'thresholds' => [
                'low' => max(0, (int) ($thresholds['low'] ?? 4)),
                'high' => max(
                    (int) ($thresholds['low'] ?? 4),
                    (int) ($thresholds['high'] ?? 8)
                ),
            ],
        ];
    }

    /** @return array<string, string> */
    public static function defaultAcceptanceRules(): array
    {
        return [
            'low' => 'justification_required_for_retain',
            'medium' => 'justification_required_for_retain',
            'high' => 'treatment_required_before_acceptance',
            'critical' => 'treatment_required_before_acceptance',
        ];
    }

    /** @param array<int, array<string, mixed>> $comments @return array<int, array<string, mixed>> */
    private function orderedComments(array $comments): array
    {
        usort($comments, static fn (array $left, array $right): int => $left['scaleIndex'] <=> $right['scaleIndex']);

        return $comments;
    }

    /** @param array<string, mixed> $scale @param array<string, mixed>|null $type @param array<string, mixed> $comment @return array<string, mixed> */
    private function level(array $scale, ?array $type, array $comment, int $value): array
    {
        $label = trim((string) $comment['comment']);

        return [
            'value' => $value,
            'label' => $label,
            'description' => $label,
            'source' => [
                'scaleId' => (int) $scale['id'],
                'scaleTypeId' => $type === null ? null : (int) $type['id'],
                'commentId' => (int) $comment['id'],
                'scaleIndex' => (int) $comment['scaleIndex'],
                'scaleValue' => (int) $comment['scaleValue'],
                'min' => (int) $scale['min'],
                'max' => (int) $scale['max'],
                'label' => $label,
                'comment' => $label,
            ],
        ];
    }

    /** @param array<int, array<string, mixed>> $likelihood @param array<int, array<string, mixed>> $dimensions @return array<int, string> */
    private function guidance(array $likelihood, array $dimensions): array
    {
        $guidance = [];
        if (!$this->completeLevels($likelihood, true)) {
            $guidance[] = 'The current operational-risk likelihood scale must provide ordered visible levels.';
        }
        if ($dimensions === []) {
            $guidance[] = 'The current operational-risk impact scale must provide at least one visible, '
                . 'labelled impact dimension.';
        }
        foreach ($dimensions as $dimension) {
            if (trim((string) $dimension['label']) === '' || !$this->completeLevels($dimension['levels'], false)) {
                $guidance[] = sprintf(
                    'The operational-risk impact dimension "%s" must provide ordered visible levels.',
                    $dimension['label'] === '' ? 'without a label' : $dimension['label']
                );
            }
        }

        return $guidance;
    }

    /** @param array<int, array<string, mixed>> $levels */
    private function completeLevels(array $levels, bool $likelihood): bool
    {
        if ($levels === [] || count($levels) > 20) {
            return false;
        }
        $previous = null;
        foreach ($levels as $offset => $level) {
            if (($likelihood && (int) $level['value'] !== $offset)
                || (!$likelihood && $previous !== null && (int) $level['value'] <= $previous)
                || trim((string) $level['label']) === '') {
                return false;
            }
            $previous = (int) $level['value'];
        }

        return true;
    }

    private static function approvedBand(int $likelihood, int $consequence): string
    {
        if ($likelihood >= 4 && $consequence >= 4) {
            return 'critical';
        }
        if (($likelihood >= 4 && $consequence >= 3) || ($consequence >= 4 && $likelihood >= 3)) {
            return 'high';
        }
        if (($likelihood >= 3 && $consequence >= 2) || ($consequence >= 3 && $likelihood >= 2)) {
            return 'medium';
        }

        return 'low';
    }
}
