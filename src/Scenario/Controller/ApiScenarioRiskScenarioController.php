<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Scenario\Controller;

use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\Response\JsonResponse;
use Monarc\Core\Controller\Handler\AbstractRestfulControllerRequestHandler;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\Core\Scenario\Feature\ScenarioCapability;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Scenario\Exception\ScenarioRiskStoryException;
use Monarc\FrontOffice\Scenario\Service\ScenarioRiskStoryService;
use Monarc\FrontOffice\Scenario\Validator\ScenarioRiskScenarioUpdateValidator;
use Monarc\FrontOffice\Scenario\Validator\ScenarioRiskScenarioValidator;

/** Exposes the small protected API for editable Scenario risk stories. */
final class ApiScenarioRiskScenarioController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;

    public function __construct(
        private ScenarioCapability $capability,
        private ScenarioRiskStoryService $service,
        private ScenarioRiskScenarioValidator $validator,
        private ScenarioRiskScenarioUpdateValidator $updateValidator
    ) {
    }

    public function getList()
    {
        $this->capability->assertEnabled();
        $anr = $this->scenarioAnr();
        if ($anr === null) {
            return $this->notFound();
        }

        return $this->getPreparedJsonResponse($this->service->list(
            $anr,
            max(1, (int) $this->params()->fromQuery('page', 1)),
            min(100, max(1, (int) $this->params()->fromQuery('pageSize', 25)))
        ));
    }

    public function get($id)
    {
        $this->capability->assertEnabled();
        $anr = $this->scenarioAnr();
        if ($anr === null) {
            return $this->notFound();
        }
        $story = $this->service->get($anr, (string) $id);
        if ($story === null) {
            return $this->notFound();
        }
        if ($this->resource() !== null && !in_array($this->resource(), [
            'risk-source',
            'cause',
            'events',
            'consequences',
            'edges',
            'event-consequences',
            'subject-links',
            'control-links',
            'completeness',
        ], true)) {
            return $this->resourceNotFound();
        }

        return $this->getPreparedJsonResponse($this->readResource($story));
    }

    public function create($data)
    {
        $this->capability->assertEnabled();
        $anr = $this->scenarioAnr();
        if ($anr === null) {
            return $this->notFound();
        }
        $resource = $this->resource();
        if ($resource === null) {
            if (!$this->hasOnlyFields($data, [
                'title', 'sourceSnapshotUuid', 'sourceInstanceRecordUuid', 'provenance',
            ])) {
                return $this->invalidPayload();
            }
            $this->validatePostParams($this->validator, $data);

            return $this->getSuccessfulJsonResponse($this->service->create(
                $anr,
                $this->validator->getValidData(),
                $this->correlationId()
            ));
        }

        $storyUuid = (string) $this->params()->fromRoute('id');
        if ($this->service->get($anr, $storyUuid) === null) {
            return $this->notFound();
        }
        $revision = $this->ifMatchRevision();
        if ($revision === null) {
            return $this->revisionConflict();
        }

        try {
            $result = match ($resource) {
                'risk-source' => $this->service->saveSource(
                    $anr,
                    $storyUuid,
                    $this->sourcePayload($data, true),
                    $revision,
                    $this->correlationId()
                ),
                'cause' => $this->service->saveCause(
                    $anr,
                    $storyUuid,
                    $this->causePayload($data, true),
                    $revision,
                    $this->correlationId()
                ),
                'events', 'consequences' => $this->service->createNode(
                    $anr,
                    $storyUuid,
                    $resource === 'events' ? 'event' : 'consequence',
                    $this->nodePayload($data),
                    $revision,
                    $this->correlationId()
                ),
                'edges', 'event-consequences', 'subject-links', 'control-links' => $this->service->createRelation(
                    $anr,
                    $storyUuid,
                    $this->relationType($resource),
                    $this->relationPayload($resource, $data),
                    $revision,
                    $this->correlationId()
                ),
                default => throw new ScenarioRiskStoryException(
                    'invalid_resource',
                    'The Scenario resource is invalid.'
                ),
            };
        } catch (ScenarioRiskStoryException $exception) {
            return $this->domainError($exception);
        }

        return $result === null
            ? $this->revisionConflict()
            : $this->getSuccessfulJsonResponse($result);
    }

    public function patch($id, $data)
    {
        $this->capability->assertEnabled();
        $anr = $this->scenarioAnr();
        if ($anr === null || $this->service->get($anr, (string) $id) === null) {
            return $this->notFound();
        }
        $revision = $this->ifMatchRevision();
        if ($revision === null) {
            return $this->revisionConflict();
        }
        $resource = $this->resource();

        try {
            if ($resource === null) {
                if (!$this->hasOnlyFields($data, [
                    'title', 'sourceSnapshotUuid', 'sourceInstanceRecordUuid', 'provenance',
                ]) || $data === []) {
                    return $this->invalidPayload();
                }
                $this->validatePostParams($this->updateValidator, $data);
                $result = $this->service->update(
                    $anr,
                    (string) $id,
                    $this->updateValidator->getValidData(),
                    $revision,
                    $this->correlationId()
                );
            } elseif ($resource === 'risk-source') {
                $result = $this->service->saveSource(
                    $anr,
                    (string) $id,
                    $this->sourcePayload($data),
                    $revision,
                    $this->correlationId()
                );
            } elseif ($resource === 'cause') {
                $result = $this->service->saveCause(
                    $anr,
                    (string) $id,
                    $this->causePayload($data),
                    $revision,
                    $this->correlationId()
                );
            } elseif (in_array($resource, ['events', 'consequences'], true)
                && $this->resourceId() !== null) {
                $result = $this->service->updateNode(
                    $anr,
                    (string) $id,
                    $resource === 'events' ? 'event' : 'consequence',
                    $this->resourceId(),
                    $this->nodePayload($data, false),
                    $revision,
                    $this->correlationId()
                );
            } else {
                return $this->invalidPayload();
            }
        } catch (ScenarioRiskStoryException $exception) {
            return $this->domainError($exception);
        }

        return $result === null
            ? $this->revisionConflict()
            : $this->getSuccessfulJsonResponse($result);
    }

    public function delete($id)
    {
        $this->capability->assertEnabled();
        $anr = $this->scenarioAnr();
        if ($anr === null || $this->service->get($anr, (string) $id) === null) {
            return $this->notFound();
        }
        $revision = $this->ifMatchRevision();
        if ($revision === null) {
            return $this->revisionConflict();
        }
        $resource = $this->resource();
        if ($resource === null) {
            $deleted = $this->service->delete($anr, (string) $id, $revision, $this->correlationId());
        } elseif (in_array($resource, ['events', 'consequences'], true) && $this->resourceId() !== null) {
            $deleted = $this->service->deleteNode(
                $anr,
                (string) $id,
                $resource === 'events' ? 'event' : 'consequence',
                $this->resourceId(),
                $revision,
                $this->correlationId()
            );
        } elseif (in_array($resource, ['edges', 'event-consequences', 'subject-links', 'control-links'], true)
            && $this->resourceId() !== null) {
            $deleted = $this->service->deleteRelation(
                $anr,
                (string) $id,
                $this->relationType($resource),
                $this->resourceId(),
                $revision,
                $this->correlationId()
            );
        } else {
            return $this->invalidPayload();
        }

        return $deleted ? new EmptyResponse(204) : $this->revisionConflict();
    }

    /** @param array<string, mixed> $story @return array<string, mixed> */
    private function readResource(array $story): array
    {
        return match ($this->resource()) {
            null => $story,
            'risk-source' => ['item' => $story['riskSource']],
            'cause' => ['item' => $story['cause']],
            'events' => ['items' => $story['events']],
            'consequences' => ['items' => $story['consequences']],
            'edges' => ['items' => $story['edges']],
            'event-consequences' => ['items' => $story['eventConsequences']],
            'subject-links' => ['items' => $story['subjectLinks']],
            'control-links' => ['items' => $story['controlLinks']],
            'completeness' => $this->service->completeness($this->scenarioAnr() ?? throw new \LogicException()),
            default => $story,
        };
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function sourcePayload(array $data, bool $titleRequired = false): array
    {
        $this->assertPayload($data, ['title', 'narrative', 'legacyRiskSourceId'], true);
        $this->assertStringFields($data, ['title' => 255, 'narrative' => 65535, 'legacyRiskSourceId' => 36]);
        if ($titleRequired && (!isset($data['title']) || trim((string) $data['title']) === '')) {
            throw new ScenarioRiskStoryException('invalid_payload', 'A risk-source title is required.');
        }

        return $this->trimmed($data);
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function causePayload(array $data, bool $titleRequired = false): array
    {
        $this->assertPayload($data, [
            'title', 'narrative', 'threatReferenceUuid', 'vulnerabilityReferenceUuid',
        ], true);
        $this->assertStringFields($data, [
            'title' => 255, 'narrative' => 65535, 'threatReferenceUuid' => 36, 'vulnerabilityReferenceUuid' => 36,
        ]);
        foreach (['threatReferenceUuid', 'vulnerabilityReferenceUuid'] as $field) {
            if (isset($data[$field]) && !$this->isUuid((string) $data[$field])) {
                throw new ScenarioRiskStoryException('invalid_payload', 'The reference UUID is invalid.');
            }
        }
        if ($titleRequired && (!isset($data['title']) || trim((string) $data['title']) === '')) {
            throw new ScenarioRiskStoryException('invalid_payload', 'A cause title is required.');
        }

        return $this->trimmed($data);
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function nodePayload(array $data, bool $titleRequired = true): array
    {
        $this->assertPayload($data, ['title', 'narrative'], $titleRequired);
        $this->assertStringFields($data, ['title' => 255, 'narrative' => 65535]);

        return $this->trimmed($data);
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function relationPayload(string $resource, array $data): array
    {
        $allowed = match ($resource) {
            'edges' => ['fromEventUuid', 'toEventUuid'],
            'event-consequences' => ['eventUuid', 'consequenceUuid'],
            'subject-links' => [
                'targetType', 'targetUuid', 'subjectType', 'subjectReferenceUuid', 'relationshipIntent', 'narrative',
            ],
            'control-links' => [
                'targetType', 'targetUuid', 'controlReferenceUuid', 'relationshipIntent', 'narrative',
            ],
            default => [],
        };
        $this->assertPayload($data, $allowed, true);
        $this->assertStringFields($data, array_fill_keys($allowed, 65535));
        $required = match ($resource) {
            'edges' => ['fromEventUuid', 'toEventUuid'],
            'event-consequences' => ['eventUuid', 'consequenceUuid'],
            'subject-links' => ['subjectType', 'subjectReferenceUuid'],
            'control-links' => ['controlReferenceUuid', 'relationshipIntent'],
            default => [],
        };
        foreach ($required as $field) {
            if (!isset($data[$field]) || trim((string) $data[$field]) === '') {
                throw new ScenarioRiskStoryException(
                    'invalid_payload',
                    'A required Scenario relation field is missing.'
                );
            }
        }
        $uuidFields = [
            'fromEventUuid',
            'toEventUuid',
            'eventUuid',
            'consequenceUuid',
            'targetUuid',
            'subjectReferenceUuid',
            'controlReferenceUuid',
        ];
        foreach ($uuidFields as $field) {
            if (isset($data[$field]) && !$this->isUuid((string) $data[$field])) {
                throw new ScenarioRiskStoryException('invalid_payload', 'The relation UUID is invalid.');
            }
        }
        if (isset($data['targetType'])
            && !in_array($data['targetType'], ['risk_scenario', 'event', 'consequence'], true)) {
            throw new ScenarioRiskStoryException('invalid_payload', 'The link target type is invalid.');
        }
        if (isset($data['subjectType']) && !in_array(
            $data['subjectType'],
            ['asset', 'process', 'object', 'information', 'supplier', 'objective'],
            true
        )) {
            throw new ScenarioRiskStoryException('invalid_payload', 'The subject type is invalid.');
        }
        if ($resource === 'control-links' && (!isset($data['relationshipIntent'])
            || !in_array($data['relationshipIntent'], ['existing', 'proposed'], true))) {
            throw new ScenarioRiskStoryException('invalid_payload', 'The control relationship intent is required.');
        }

        return $this->trimmed($data);
    }

    /** @param array<string, mixed> $data @param array<int, string> $allowed */
    private function assertPayload(array $data, array $allowed, bool $required): void
    {
        if (!$this->hasOnlyFields($data, $allowed) || ($required && $data === [])) {
            throw new ScenarioRiskStoryException('invalid_payload', 'The Scenario request payload is invalid.');
        }
    }

    /** @param array<string, mixed> $data @param array<string, int> $fields */
    private function assertStringFields(array $data, array $fields): void
    {
        foreach ($fields as $field => $maxLength) {
            if (isset($data[$field]) && (!is_string($data[$field]) || mb_strlen(trim($data[$field])) > $maxLength)) {
                throw new ScenarioRiskStoryException('invalid_payload', 'The Scenario request payload is invalid.');
            }
        }
        if (array_key_exists('title', $data) && trim((string) $data['title']) === '') {
            throw new ScenarioRiskStoryException('invalid_payload', 'A title is required.');
        }
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function trimmed(array $data): array
    {
        foreach ($data as $field => $value) {
            if (is_string($value)) {
                $data[$field] = trim($value);
            }
        }

        return $data;
    }

    /** @param array<string, mixed> $data @param array<int, string> $allowed */
    private function hasOnlyFields($data, array $allowed): bool
    {
        return is_array($data) && array_diff(array_keys($data), $allowed) === [];
    }

    private function relationType(string $resource): string
    {
        return match ($resource) {
            'edges' => 'edge',
            'event-consequences' => 'event-consequence',
            'subject-links' => 'subject-link',
            'control-links' => 'control-link',
            default => '',
        };
    }

    private function resource(): ?string
    {
        $resource = $this->params()->fromRoute('resource');

        return is_string($resource) && $resource !== '' ? $resource : null;
    }

    private function resourceId(): ?string
    {
        $id = $this->params()->fromRoute('resourceid');

        return is_string($id) && $this->isUuid($id) ? $id : null;
    }

    private function ifMatchRevision(): ?int
    {
        $header = $this->getRequest()->getHeader('If-Match');
        $revision = $header === false ? false : filter_var(
            $header->getFieldValue(),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        return $revision === false ? null : $revision;
    }

    private function isUuid(string $value): bool
    {
        return preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/', $value) === 1;
    }

    private function scenarioAnr(): ?Anr
    {
        $anr = $this->getRequest()->getAttribute('anr');

        return $anr instanceof Anr && $anr->getAnalysisType() === Anr::ANALYSIS_TYPE_SCENARIO ? $anr : null;
    }

    private function correlationId(): string
    {
        $header = $this->getRequest()->getHeader('X-Correlation-ID');

        return $header === false ? bin2hex(random_bytes(16)) : substr($header->getFieldValue(), 0, 128);
    }

    private function domainError(ScenarioRiskStoryException $exception): JsonResponse
    {
        $status = $exception->reason() === 'unavailable_reference' ? 403 : 422;
        if (in_array($exception->reason(), ['cycle', 'self_edge', 'duplicate_relation'], true)) {
            $status = 409;
        }
        if ($exception->reason() === 'revision_conflict') {
            $status = 409;
        }

        return $this->error($status, $exception->reason(), $exception->getMessage());
    }

    private function revisionConflict(): JsonResponse
    {
        return $this->error(409, 'revision_conflict', 'Reload the risk story before retrying.');
    }

    private function invalidPayload(): JsonResponse
    {
        return $this->error(422, 'invalid_payload', 'The risk-story payload is invalid.');
    }

    private function notFound(): JsonResponse
    {
        return $this->error(404, 'not_found', 'Scenario analysis or risk story was not found.');
    }

    private function resourceNotFound(): JsonResponse
    {
        return $this->error(404, 'not_found', 'Scenario resource was not found.');
    }

    private function error(int $status, string $code, string $message): JsonResponse
    {
        return new JsonResponse(['error' => ['code' => $code, 'message' => $message]], $status);
    }
}
