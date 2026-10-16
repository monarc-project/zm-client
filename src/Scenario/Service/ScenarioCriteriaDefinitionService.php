<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Scenario\Service;

use Monarc\Core\Exception\ActionForbiddenException;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Entity\User;
use Monarc\FrontOffice\Scenario\Table\ScenarioCriteriaDefinitionTable;

/** Coordinates the Context Establishment criteria definition without changing legacy operational scales. */
final class ScenarioCriteriaDefinitionService
{
    public function __construct(
        private OperationalRiskCriteriaAdapter $operationalRiskCriteria,
        private ScenarioCriteriaDefinitionTable $criteria,
        private ScenarioQualitativeCalculationService $calculator,
        private ConnectedUserService $users
    ) {
    }

    /** @return array<string, mixed> */
    public function source(Anr $anr): array
    {
        return $this->operationalRiskCriteria->source($anr);
    }

    /** @return array<string, mixed> */
    public function definition(Anr $anr): array
    {
        $locked = $this->criteria->locked((int) $anr->getId());
        if ($locked !== null) {
            return ['status' => 'locked', 'locked' => $locked];
        }
        $draft = $this->criteria->draft((int) $anr->getId());

        if ($draft !== null && !isset($draft['bands']['thresholds'])) {
            $draft['bands']['thresholds'] = $this->operationalRiskCriteria->thresholds($anr);
        }

        return $draft === null ? ['status' => 'empty'] : ['status' => 'draft', 'draft' => $draft];
    }

    /** @return array<string, mixed>|null */
    public function createDraft(Anr $anr, int $analysisRevision, string $correlationId): ?array
    {
        $source = $this->operationalRiskCriteria->source($anr);
        if (!$source['complete']) {
            throw new \InvalidArgumentException(implode(' ', $source['guidance']));
        }

        return $this->criteria->createDraft(
            (int) $anr->getId(),
            $this->operationalRiskCriteria->draftInput($source),
            $analysisRevision,
            $this->actorId(),
            $correlationId
        );
    }

    /** @param array<string, mixed> $data @return array<string, mixed>|null */
    public function updateDraft(Anr $anr, array $data, int $draftRevision, string $correlationId): ?array
    {
        $this->assertDraftIsComplete($data);

        return $this->criteria->updateDraft(
            (int) $anr->getId(),
            $data,
            $draftRevision,
            $this->actorId(),
            $correlationId
        );
    }

    /** @return array<string, mixed>|null */
    public function lock(Anr $anr, int $draftRevision, string $correlationId): ?array
    {
        $definition = $this->definition($anr);
        if ($definition['status'] !== 'draft') {
            throw new ActionForbiddenException(
                'Scenario criteria cannot be locked without an editable criteria draft.'
            );
        }
        $draft = $definition['draft'];
        $this->assertDraftIsComplete($draft);
        $profile = $this->profileFromDraft($draft);

        return $this->criteria->lockDraft(
            (int) $anr->getId(),
            $draftRevision,
            $profile,
            $this->actorId(),
            $correlationId
        );
    }

    /** @param array<string, mixed> $draft */
    private function assertDraftIsComplete(array $draft): void
    {
        $likelihood = array_values(array_filter(
            $draft['likelihoodLevels'],
            static fn (array $level): bool => $level['enabled']
        ));
        $dimensions = array_values(array_filter(
            $draft['dimensions'],
            static fn (array $dimension): bool => $dimension['enabled']
        ));
        if ($likelihood === [] || $dimensions === []) {
            throw new \InvalidArgumentException(
                'Enable at least one likelihood level and one impact dimension before locking criteria.'
            );
        }
        $impactLevelCount = null;
        foreach ($dimensions as $dimension) {
            $levels = array_values(array_filter(
                $dimension['levels'],
                static fn (array $level): bool => $level['enabled']
            ));
            if ($levels === []) {
                throw new \InvalidArgumentException(
                    'Each enabled impact dimension must contain at least one enabled ordered level.'
                );
            }
            if ($impactLevelCount !== null && count($levels) !== $impactLevelCount) {
                throw new \InvalidArgumentException(
                    'All enabled impact dimensions must use the same number of levels.'
                );
            }
            if (isset($impactValues) && $impactValues !== array_column($levels, 'value')) {
                throw new \InvalidArgumentException(
                    'All enabled impact dimensions must use the same ordered scale values.'
                );
            }
            $impactLevelCount = count($levels);
            $impactValues = array_column($levels, 'value');
        }
        $this->calculator->validateProfile($this->profileFromDraft($draft)['payload']);
    }

    /** @param array<string, mixed> $draft @return array<string, mixed> */
    private function profileFromDraft(array $draft): array
    {
        $identifier = (string) ($draft['identifier'] ?? 'draft');
        $likelihood = $this->profileLabels($draft['likelihoodLevels']);
        $dimensions = [];
        $dimensionLabels = [];
        $dimensionLevels = [];
        foreach ($draft['dimensions'] as $dimension) {
            if (!$dimension['enabled']) {
                continue;
            }
            $dimensions[] = $dimension['key'];
            $dimensionLabels[$dimension['key']] = $dimension['label'];
            $dimensionLevels[$dimension['key']] = $this->profileLabels($dimension['levels']);
        }

        return [
            'identifier' => $identifier,
            'title' => $draft['title'],
            'payload' => [
                'identifier' => $identifier,
                'title' => $draft['title'],
                'likelihoodLabels' => $likelihood,
                'consequenceLabels' => reset($dimensionLevels) ?: [],
                'dimensions' => $dimensions,
                'dimensionLabels' => $dimensionLabels,
                'dimensionLevels' => $dimensionLevels,
                'aggregationPolicy' => $draft['aggregationPolicy'],
                'bands' => $draft['bands'],
                'acceptanceRules' => $draft['acceptanceRules'],
            ],
        ];
    }

    /** @param array<int, array<string, mixed>> $levels @return array<int, array<string, mixed>> */
    private function profileLabels(array $levels): array
    {
        $enabled = array_values(array_filter($levels, static fn (array $level): bool => $level['enabled']));
        usort($enabled, static fn (array $left, array $right): int => $left['value'] <=> $right['value']);

        return array_map(static fn (array $level): array => [
            'value' => (int) $level['value'],
            'label' => trim((string) $level['label']),
            'description' => $level['description'],
        ], $enabled);
    }

    private function actorId(): int
    {
        $user = $this->users->getConnectedUser();
        if (!$user instanceof User) {
            throw new ActionForbiddenException('Scenario criteria access is forbidden.');
        }

        return (int) $user->getId();
    }
}
