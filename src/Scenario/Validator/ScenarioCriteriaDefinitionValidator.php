<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Scenario\Validator;

/** Validates the narrow Criteria Definition BFF contract before it reaches the Scenario service. */
final class ScenarioCriteriaDefinitionValidator
{
    /** @param mixed $data @return array<string, mixed> */
    public function createDraft(mixed $data): array
    {
        if (!is_array($data) || $data !== []) {
            throw new \InvalidArgumentException('The criteria-draft creation payload is invalid.');
        }

        return [];
    }

    /** @param mixed $data @return array<string, mixed> */
    public function updateDraft(mixed $data): array
    {
        if (!is_array($data)
            || array_diff(array_keys($data), [
                'title', 'aggregationPolicy', 'bands', 'acceptanceRules', 'likelihoodLevels', 'dimensions',
            ]) !== []
            || array_diff([
                'title', 'aggregationPolicy', 'bands', 'acceptanceRules', 'likelihoodLevels', 'dimensions',
            ], array_keys($data)) !== []) {
            throw new \InvalidArgumentException('The criteria-draft payload is invalid.');
        }
        if (!is_string($data['title']) || trim($data['title']) === '' || mb_strlen($data['title']) > 255
            || $data['aggregationPolicy'] !== 'highest_consequence_dimension'
            || !is_array($data['bands']) || !is_array($data['acceptanceRules'])
            || !$this->levels($data['likelihoodLevels'], true)
            || !is_array($data['dimensions']) || $data['dimensions'] === []) {
            throw new \InvalidArgumentException('The criteria-draft payload is invalid.');
        }
        foreach ($data['dimensions'] as $dimension) {
            if (!is_array($dimension)
                || array_diff(array_keys($dimension), ['uuid', 'key', 'label', 'enabled', 'source', 'levels']) !== []
                || array_diff(['uuid', 'key', 'label', 'enabled', 'levels'], array_keys($dimension)) !== []
                || !is_string($dimension['uuid']) || !$this->uuid($dimension['uuid'])
                || !is_string($dimension['key']) || trim($dimension['key']) === ''
                || !is_string($dimension['label']) || trim($dimension['label']) === ''
                || mb_strlen($dimension['label']) > 255
                || !is_bool($dimension['enabled'])
                || (array_key_exists('source', $dimension) && !$this->dimensionSource($dimension['source']))
                || !$this->levels($dimension['levels'], false)) {
                throw new \InvalidArgumentException('The criteria-draft impact dimensions are invalid.');
            }
        }

        return $data;
    }

    /** @param mixed $data @return array<string, mixed> */
    public function lock(mixed $data): array
    {
        if (!is_array($data) || $data !== []) {
            throw new \InvalidArgumentException('The criteria-lock payload is invalid.');
        }

        return [];
    }

    /** @param mixed $levels */
    private function levels(mixed $levels, bool $likelihood): bool
    {
        if (!is_array($levels) || $levels === [] || count($levels) > 20) {
            return false;
        }
        $previousValue = null;
        foreach ($levels as $level) {
            if (!is_array($level)
                || array_diff(array_keys($level), ['uuid', 'value', 'label', 'description', 'enabled', 'source']) !== []
                || array_diff(['uuid', 'value', 'label', 'description', 'enabled'], array_keys($level)) !== []
                || !is_string($level['uuid']) || !$this->uuid($level['uuid'])
                || !is_int($level['value']) || $level['value'] < 0 || $level['value'] > 255
                || !is_string($level['label']) || trim($level['label']) === '' || mb_strlen($level['label']) > 65535
                || !($level['description'] === null || is_string($level['description']))
                || !is_bool($level['enabled'])
                || (array_key_exists('source', $level) && !$this->levelSource($level['source']))) {
                return false;
            }
            if ($previousValue !== null && $level['value'] <= $previousValue) {
                return false;
            }
            $previousValue = $level['value'];
        }
        if ($likelihood && array_column($levels, 'value') !== range(0, count($levels) - 1)) {
            return false;
        }

        return true;
    }

    private function dimensionSource(mixed $source): bool
    {
        return is_array($source)
            && is_int($source['scaleId'] ?? null)
            && $source['scaleId'] > 0
            && is_int($source['scaleTypeId'] ?? null)
            && $source['scaleTypeId'] > 0
            && is_string($source['label'] ?? null)
            && trim($source['label']) !== '';
    }

    private function levelSource(mixed $source): bool
    {
        return is_array($source)
            && is_int($source['scaleId'] ?? null)
            && $source['scaleId'] > 0
            && (is_int($source['scaleTypeId'] ?? null) || $source['scaleTypeId'] === null)
            && is_int($source['commentId'] ?? null)
            && is_int($source['scaleIndex'] ?? null)
            && is_int($source['scaleValue'] ?? null)
            && is_int($source['min'] ?? null)
            && is_int($source['max'] ?? null)
            && is_string($source['label'] ?? null)
            && (($source['comment'] ?? null) === null || is_string($source['comment']));
    }

    private function uuid(string $value): bool
    {
        return preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/', $value) === 1;
    }
}
