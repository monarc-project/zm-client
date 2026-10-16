<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Scenario\Validator;

/** Validates the narrow Scenario treatment-plan write contracts before service state transitions. */
final class ScenarioTreatmentValidator
{
    /** @param mixed $data @return array<string, mixed> */
    public static function create(mixed $data): array
    {
        $data = self::record($data, [
            'treatmentType', 'actionText', 'ownerId', 'dueAt', 'status', 'recommendationReferences',
            'recommendationSelections', 'completionEvidence', 'cancellationRationale',
        ], ['treatmentType', 'actionText', 'status']);
        if (!in_array($data['treatmentType'], ['avoid', 'modify', 'share', 'retain'], true)
            || !self::nonEmpty($data['actionText'])
            || !in_array($data['status'], ['planned', 'in_progress', 'completed', 'cancelled'], true)
            || !self::owner($data['ownerId'] ?? null)
            || !self::date($data['dueAt'] ?? null)
            || !self::recommendations($data['recommendationReferences'] ?? [])
            || !self::recommendationSelections($data['recommendationSelections'] ?? [])
            || !self::matchingRecommendations($data)) {
            throw new \InvalidArgumentException('The treatment payload is invalid.');
        }
        self::optionalText($data, 'completionEvidence');
        self::optionalText($data, 'cancellationRationale');

        return $data;
    }

    /** @param mixed $data @return array<string, mixed> */
    public static function treatmentUpdate(mixed $data): array
    {
        $data = self::record($data, [
            'treatmentType', 'actionText', 'ownerId', 'dueAt', 'status', 'completionEvidence', 'cancellationRationale',
        ], []);
        if ($data === []
            || (isset($data['treatmentType'])
                && !in_array($data['treatmentType'], ['avoid', 'modify', 'share', 'retain'], true))
            || (isset($data['actionText']) && !self::nonEmpty($data['actionText']))
            || (isset($data['ownerId']) && !self::owner($data['ownerId']))
            || (array_key_exists('dueAt', $data) && !self::date($data['dueAt']))
            || (isset($data['status'])
                && !in_array($data['status'], ['planned', 'in_progress', 'completed', 'cancelled'], true))
        ) {
            throw new \InvalidArgumentException('The treatment update payload is invalid.');
        }
        self::optionalText($data, 'completionEvidence');
        self::optionalText($data, 'cancellationRationale');

        return $data;
    }

    /** @param mixed $data @return array<string, mixed> */
    public static function implementationUpdate(mixed $data): array
    {
        $data = self::record($data, [
            'status', 'ownerId', 'targetAt', 'workingNote', 'completionEvidence', 'notApplicableRationale',
        ], []);
        if ($data === []
            || (isset($data['status']) && !in_array($data['status'], [
                'not_started', 'in_progress', 'implemented', 'not_applicable',
            ], true))
            || (isset($data['ownerId']) && !self::owner($data['ownerId']))
            || (array_key_exists('targetAt', $data) && !self::date($data['targetAt']))) {
            throw new \InvalidArgumentException('The recommendation implementation payload is invalid.');
        }
        self::optionalText($data, 'workingNote');
        self::optionalText($data, 'completionEvidence');
        self::optionalText($data, 'notApplicableRationale');

        return $data;
    }

    /** @param mixed $data @param array<int, string> $allowed @param array<int, string> $required @return array<string, mixed> */
    private static function record(mixed $data, array $allowed, array $required): array
    {
        if (!is_array($data)
            || array_diff(array_keys($data), $allowed) !== []
            || array_diff($required, array_keys($data)) !== []
        ) {
            throw new \InvalidArgumentException('The Scenario request payload is invalid.');
        }

        return $data;
    }

    private static function nonEmpty(mixed $value): bool
    {
        return is_string($value) && trim($value) !== '';
    }

    private static function owner(mixed $value): bool
    {
        return $value === null || $value === '' || is_int($value) && $value > 0
            || is_string($value) && ctype_digit($value) && (int) $value > 0;
    }

    private static function date(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }
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

    private static function recommendations(mixed $value): bool
    {
        if (!is_array($value) || count($value) !== count(array_unique($value))) {
            return false;
        }

        foreach ($value as $uuid) {
            if (!is_string($uuid) || !self::uuid($uuid)) {
                return false;
            }
        }

        return true;
    }

    private static function recommendationSelections(mixed $value): bool
    {
        if (!is_array($value)) {
            return false;
        }

        $uuids = [];
        foreach ($value as $selection) {
            if (!is_array($selection)
                || array_diff(array_keys($selection), ['recommendationUuid', 'treatmentImportance']) !== []
                || array_diff(['recommendationUuid', 'treatmentImportance'], array_keys($selection)) !== []
                || !is_string($selection['recommendationUuid'] ?? null)
                || !self::uuid($selection['recommendationUuid'])
                || !is_int($selection['treatmentImportance'] ?? null)
                || $selection['treatmentImportance'] < 0
                || $selection['treatmentImportance'] > 3
            ) {
                return false;
            }
            $uuids[] = $selection['recommendationUuid'];
        }

        return count($uuids) === count(array_unique($uuids));
    }

    /** @param array<string, mixed> $data */
    private static function matchingRecommendations(array $data): bool
    {
        if (!isset($data['recommendationReferences'], $data['recommendationSelections'])) {
            return true;
        }

        return $data['recommendationReferences'] === array_column(
            $data['recommendationSelections'],
            'recommendationUuid'
        );
    }

    /** @param array<string, mixed> $data */
    private static function optionalText(array $data, string $field): void
    {
        if (array_key_exists($field, $data) && $data[$field] !== null && !is_string($data[$field])) {
            throw new \InvalidArgumentException('The Scenario request payload is invalid.');
        }
    }

    private static function uuid(string $value): bool
    {
        return preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/', $value) === 1;
    }
}
