<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Scenario\Validator;

/** Validates the intentionally small, read-only Scenario risk-register query language. */
final class ScenarioRiskRegisterQuery
{
    /** @var array<int, string> */
    private const FIELDS = [
        'page',
        'pageSize',
        'text',
        'lifecycle',
        'completeness',
        'band',
        'profileVersion',
        'treatmentState',
        'reviewState',
        'triggerOccurred',
        'acceptanceState',
        'subject',
        'sort',
        'direction',
    ];

    /**
     * @param array<string, mixed> $query
     * @return array<string, int|string|bool|null>
     */
    public static function validate(array $query): array
    {
        if (array_diff(array_keys($query), self::FIELDS) !== []) {
            throw new \InvalidArgumentException('The Scenario risk-register query contains an unsupported field.');
        }

        $page = self::positiveInt($query['page'] ?? 1, 1, 100000, 'page');
        $pageSize = self::positiveInt($query['pageSize'] ?? 25, 1, 100, 'pageSize');
        $text = self::text($query['text'] ?? null, 120, 'text');
        $subject = self::text($query['subject'] ?? null, 120, 'subject');
        $lifecycle = self::enum($query['lifecycle'] ?? null, ['draft', 'active', 'monitored', 'closed', 'archived'], 'lifecycle');
        $completeness = self::enum($query['completeness'] ?? null, ['complete', 'incomplete'], 'completeness');
        $band = self::enum($query['band'] ?? null, ['low', 'medium', 'high', 'critical'], 'band');
        $treatmentState = self::enum($query['treatmentState'] ?? null, ['absent', 'action_required', 'complete'], 'treatmentState');
        $reviewState = self::enum(
            $query['reviewState'] ?? null,
            ['not_scheduled', 'upcoming', 'due', 'overdue', 'active'],
            'reviewState'
        );
        $acceptanceState = self::enum(
            $query['acceptanceState'] ?? null,
            ['absent', 'current', 'decision_required', 'stale'],
            'acceptanceState'
        );
        $triggerOccurred = self::boolean($query['triggerOccurred'] ?? null, 'triggerOccurred');
        $profileVersion = self::uuid($query['profileVersion'] ?? null, 'profileVersion');
        $sort = self::enum($query['sort'] ?? 'updatedAt', ['title', 'updatedAt', 'lifecycle'], 'sort');
        $direction = self::enum($query['direction'] ?? 'desc', ['asc', 'desc'], 'direction');

        return compact(
            'page',
            'pageSize',
            'text',
            'subject',
            'lifecycle',
            'completeness',
            'band',
            'profileVersion',
            'treatmentState',
            'reviewState',
            'triggerOccurred',
            'acceptanceState',
            'sort',
            'direction'
        );
    }

    private static function positiveInt(mixed $value, int $minimum, int $maximum, string $field): int
    {
        if (!is_int($value) && !is_string($value)) {
            throw new \InvalidArgumentException('The Scenario risk-register ' . $field . ' is invalid.');
        }
        $parsed = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => $minimum, 'max_range' => $maximum]]);
        if ($parsed === false) {
            throw new \InvalidArgumentException('The Scenario risk-register ' . $field . ' is invalid.');
        }

        return (int) $parsed;
    }

    private static function text(mixed $value, int $maximum, string $field): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_string($value) || mb_strlen(trim($value)) > $maximum) {
            throw new \InvalidArgumentException('The Scenario risk-register ' . $field . ' is invalid.');
        }

        return trim($value);
    }

    /** @param array<int, string> $allowed */
    private static function enum(mixed $value, array $allowed, string $field): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_string($value) || !in_array($value, $allowed, true)) {
            throw new \InvalidArgumentException('The Scenario risk-register ' . $field . ' is invalid.');
        }

        return $value;
    }

    private static function boolean(mixed $value, string $field): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value === 'true' || $value === true || $value === '1' || $value === 1) {
            return true;
        }
        if ($value === 'false' || $value === false || $value === '0' || $value === 0) {
            return false;
        }

        throw new \InvalidArgumentException('The Scenario risk-register ' . $field . ' is invalid.');
    }

    private static function uuid(mixed $value, string $field): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_string($value) || preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/', $value) !== 1) {
            throw new \InvalidArgumentException('The Scenario risk-register ' . $field . ' is invalid.');
        }

        return $value;
    }
}
