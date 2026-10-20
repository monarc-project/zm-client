<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Scenario\Validator;

/** Validates the narrow review-case and trigger-occurrence API contract. */
final class ScenarioReviewValidator
{
    /** @param mixed $data @return array<string, mixed> */
    public static function createCase(mixed $data): array
    {
        $data = self::record($data, ['reason', 'analysisWide', 'riskScenarioUuids', 'idempotencyKey'], [
            'reason', 'analysisWide', 'riskScenarioUuids', 'idempotencyKey',
        ]);
        if (!in_array($data['reason'], ['scheduled', 'manual'], true)
            || !is_bool($data['analysisWide'])
            || !self::uuids($data['riskScenarioUuids'])
            || !is_string($data['idempotencyKey'])
            || !self::uuid($data['idempotencyKey'])
            || (!$data['analysisWide'] && $data['riskScenarioUuids'] === [])) {
            throw new \InvalidArgumentException('The Scenario review case payload is invalid.');
        }
        if ($data['reason'] === 'scheduled' && !$data['analysisWide']) {
            throw new \InvalidArgumentException('A scheduled review must be analysis-wide.');
        }

        return $data + ['occurrenceAt' => null];
    }

    /** @param mixed $data @return array<string, mixed> */
    public static function triggerOccurrence(mixed $data): array
    {
        $data = self::record($data, [
            'triggerId', 'analysisWide', 'riskScenarioUuids', 'occurredAt', 'sourceObservationUuid', 'sourceText',
            'triageRationale', 'idempotencyKey',
        ], ['triggerId', 'analysisWide', 'riskScenarioUuids', 'occurredAt', 'triageRationale', 'idempotencyKey']);
        if (!self::positiveInt($data['triggerId'])
            || !is_bool($data['analysisWide'])
            || !self::uuids($data['riskScenarioUuids'])
            || (!$data['analysisWide'] && $data['riskScenarioUuids'] === [])
            || !self::dateTime($data['occurredAt'])
            || (array_key_exists('sourceObservationUuid', $data)
                && (!is_string($data['sourceObservationUuid']) || !self::uuid($data['sourceObservationUuid'])))
            || (array_key_exists('sourceText', $data) && !self::text($data['sourceText'], false))
            || (!array_key_exists('sourceObservationUuid', $data)
                && (!array_key_exists('sourceText', $data) || !self::text($data['sourceText'], true)))
            || !self::text($data['triageRationale'], true)
            || !is_string($data['idempotencyKey'])
            || !self::uuid($data['idempotencyKey'])) {
            throw new \InvalidArgumentException('The Scenario trigger occurrence payload is invalid.');
        }

        return $data;
    }

    /** @param mixed $data @return array<string, mixed> */
    public static function transition(string $action, mixed $data): array
    {
        $common = ['idempotencyKey'];
        $data = match ($action) {
            'assign' => self::record($data, [...$common, 'assignedReviewerId'], [...$common, 'assignedReviewerId']),
            'scope' => self::record($data, [...$common, 'analysisWide', 'riskScenarioUuids'], [
                ...$common, 'analysisWide', 'riskScenarioUuids',
            ]),
            'dispositions' => self::record($data, [...$common, 'dispositions'], [...$common, 'dispositions']),
            'complete' => self::record($data, [...$common, 'satisfiesScheduledReview', 'rationale'], [
                ...$common, 'satisfiesScheduledReview',
            ]),
            'dismiss' => self::record($data, [...$common, 'rationale'], [...$common, 'rationale']),
            default => throw new \InvalidArgumentException('The Scenario review action is invalid.'),
        };
        if (!is_string($data['idempotencyKey']) || !self::uuid($data['idempotencyKey'])) {
            throw new \InvalidArgumentException('The Scenario review transition is invalid.');
        }
        if ($action === 'assign' && !self::positiveInt($data['assignedReviewerId'])) {
            throw new \InvalidArgumentException('The selected review owner is invalid.');
        }
        if ($action === 'scope'
            && (!is_bool($data['analysisWide'])
                || !self::uuids($data['riskScenarioUuids'])
                || (!$data['analysisWide'] && $data['riskScenarioUuids'] === []))) {
            throw new \InvalidArgumentException('The Scenario review scope is invalid.');
        }
        if ($action === 'dispositions') {
            $data['dispositions'] = self::dispositions($data['dispositions']);
        }
        if ($action === 'complete'
            && (!is_bool($data['satisfiesScheduledReview'])
                || (isset($data['rationale']) && !self::text($data['rationale'], false)))) {
            throw new \InvalidArgumentException('The Scenario review completion is invalid.');
        }
        if ($action === 'dismiss' && !self::text($data['rationale'], true)) {
            throw new \InvalidArgumentException('Dismissing a review case requires a rationale.');
        }

        return $data;
    }

    /** @param mixed $value @return array<int, array<string, mixed>> */
    private static function dispositions(mixed $value): array
    {
        if (!is_array($value) || $value === []) {
            throw new \InvalidArgumentException('Record at least one Scenario review disposition.');
        }
        $seen = [];
        foreach ($value as $disposition) {
            if (!is_array($disposition)
                || array_diff(array_keys($disposition), [
                    'riskScenarioUuid', 'riskScenarioRevision', 'disposition', 'rationale', 'reassessedAssessmentUuid',
                    'treatmentUuids',
                ]) !== []
                || array_diff(
                    ['riskScenarioUuid', 'riskScenarioRevision', 'disposition'],
                    array_keys($disposition)
                ) !== []
                || !is_string($disposition['riskScenarioUuid'])
                || !self::uuid($disposition['riskScenarioUuid'])
                || !self::positiveInt($disposition['riskScenarioRevision'])
                || !in_array($disposition['disposition'], [
                    'no_material_change', 'reassessed', 'treatment_follow_up', 'not_applicable',
                ], true)
                || (isset($disposition['rationale']) && !self::text($disposition['rationale'], false))
                || (isset($disposition['reassessedAssessmentUuid'])
                    && (!is_string($disposition['reassessedAssessmentUuid'])
                        || !self::uuid($disposition['reassessedAssessmentUuid'])))
                || (isset($disposition['treatmentUuids']) && !self::uuids($disposition['treatmentUuids']))
                || isset($seen[$disposition['riskScenarioUuid']])) {
                throw new \InvalidArgumentException('The Scenario review disposition is invalid.');
            }
            if ($disposition['disposition'] === 'reassessed' && !isset($disposition['reassessedAssessmentUuid'])) {
                throw new \InvalidArgumentException('A reassessed disposition requires a new assessment.');
            }
            if ($disposition['disposition'] === 'treatment_follow_up'
                && (!self::text($disposition['rationale'] ?? null, true) || empty($disposition['treatmentUuids']))) {
                throw new \InvalidArgumentException(
                    'Treatment follow-up requires a rationale and existing treatment work.'
                );
            }
            if ($disposition['disposition'] === 'not_applicable'
                && !self::text($disposition['rationale'] ?? null, true)) {
                throw new \InvalidArgumentException(
                    'A not-applicable disposition requires a rationale.'
                );
            }
            $seen[$disposition['riskScenarioUuid']] = true;
        }

        return $value;
    }

    /** @param mixed $data @param array<int, string> $allowed @param array<int, string> $required @return array<string, mixed> */
    private static function record(mixed $data, array $allowed, array $required): array
    {
        if (!is_array($data)
            || array_diff(array_keys($data), $allowed) !== []
            || array_diff($required, array_keys($data)) !== []) {
            throw new \InvalidArgumentException('The Scenario review request is invalid.');
        }

        return $data;
    }

    /** @param mixed $value */
    private static function uuids($value): bool
    {
        return is_array($value)
            && count($value) === count(array_unique($value))
            && array_filter($value, static fn ($uuid): bool => !is_string($uuid) || !self::uuid($uuid)) === [];
    }

    /** @param mixed $value */
    private static function positiveInt($value): bool
    {
        return (is_int($value) || is_string($value)) && filter_var($value, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]) !== false;
    }

    /** @param mixed $value */
    private static function dateTime($value): bool
    {
        if (!is_string($value)) {
            return false;
        }
        try {
            new \DateTimeImmutable($value);
        } catch (\Exception) {
            return false;
        }

        return true;
    }

    /** @param mixed $value */
    private static function text($value, bool $required): bool
    {
        return is_string($value) && (!$required || trim($value) !== '') && mb_strlen($value) <= 65_535;
    }

    private static function uuid(string $value): bool
    {
        return preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/', $value) === 1;
    }
}
