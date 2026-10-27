<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Scenario\Validator;

/** Validates the narrow, browser-safe command for explicit local model derivation. */
final class ScenarioTemplateModelRiskScenarioCreationValidator
{
    /** @param mixed $data @return array<string, mixed> */
    public function validate(mixed $data): array
    {
        if (!is_array($data) || array_diff(array_keys($data), ['idempotencyKey', 'sourceRevision', 'models']) !== []) {
            throw new \InvalidArgumentException('The template-model creation request is invalid.');
        }
        $key = $data['idempotencyKey'] ?? null;
        $revision = $data['sourceRevision'] ?? null;
        $models = $data['models'] ?? null;
        if (!is_string($key) || preg_match('/^[A-Za-z0-9_-]{8,128}$/', $key) !== 1
            || !is_int($revision) || $revision < 1 || !is_array($models) || $models === [] || count($models) > 20) {
            throw new \InvalidArgumentException('The template-model creation request is invalid.');
        }
        $validModels = [];
        $seenModels = [];
        foreach ($models as $model) {
            if (!is_array($model) || array_diff(array_keys($model), [
                'modelRecordUuid', 'sourceRevision', 'title', 'description', 'eventRecordUuids',
            ]) !== []) {
                throw new \InvalidArgumentException('The template-model creation request is invalid.');
            }
            $modelUuid = $model['modelRecordUuid'] ?? null;
            $sourceRevision = $model['sourceRevision'] ?? null;
            $title = $model['title'] ?? null;
            $description = $model['description'] ?? null;
            $eventRecordUuids = $model['eventRecordUuids'] ?? null;
            if (!$this->uuid($modelUuid) || isset($seenModels[$modelUuid]) || !is_int($sourceRevision)
                || $sourceRevision !== $revision || !is_string($title) || ($title = trim($title)) === ''
                || mb_strlen($title) > 255 || !is_array($eventRecordUuids) || count($eventRecordUuids) > 100
                || ($description !== null && (!is_string($description) || mb_strlen(trim($description)) > 65535))) {
                throw new \InvalidArgumentException('The template-model creation request is invalid.');
            }
            $seenModels[$modelUuid] = true;
            $events = [];
            foreach ($eventRecordUuids as $eventUuid) {
                if (!$this->uuid($eventUuid) || isset($events[$eventUuid])) {
                    throw new \InvalidArgumentException('The template-model creation request is invalid.');
                }
                $events[$eventUuid] = true;
            }
            $validModels[] = [
                'modelRecordUuid' => $modelUuid,
                'sourceRevision' => $sourceRevision,
                'title' => $title,
                'description' => $description === null ? null : trim($description),
                'eventRecordUuids' => array_keys($events),
            ];
        }

        return ['idempotencyKey' => $key, 'sourceRevision' => $revision, 'models' => $validModels];
    }

    private function uuid(mixed $value): bool
    {
        return is_string($value)
            && preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/', $value) === 1;
    }
}
