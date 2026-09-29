<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Scenario\Controller;

use Laminas\Diactoros\Response\JsonResponse;
use Monarc\Core\Controller\Handler\AbstractRestfulControllerRequestHandler;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\Core\Exception\ActionForbiddenException;
use Monarc\Core\Exception\Exception as CoreException;
use Monarc\Core\Scenario\Feature\ScenarioCapability;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Scenario\Service\ScenarioAssessmentService;
use Monarc\FrontOffice\Scenario\Service\ScenarioRiskStoryService;

/** Exposes the protected, backend-only SRA-13 criteria and decision API. */
final class ApiScenarioAssessmentController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;

    public function __construct(
        private ScenarioCapability $capability,
        private ScenarioAssessmentService $assessments,
        private ScenarioRiskStoryService $stories
    ) {
    }

    public function getList()
    {
        $this->capability->assertEnabled();
        $anr = $this->scenarioAnr();
        if ($anr === null) {
            return $this->notFound();
        }

        if ($this->kind() === 'profiles') {
            return $this->resource() === 'assigned'
                ? $this->getPreparedJsonResponse(['item' => $this->assessments->assignedProfile($anr)])
                : $this->getPreparedJsonResponse($this->assessments->listProfiles());
        }

        return $this->notFound();
    }

    public function get($id)
    {
        $this->capability->assertEnabled();
        $anr = $this->scenarioAnr();
        if ($anr === null) {
            return $this->notFound();
        }
        if ($this->kind() === 'profiles') {
            return $this->notFound();
        }
        $storyUuid = (string) $id;
        if ($this->stories->get($anr, $storyUuid) === null) {
            return $this->notFound();
        }

        return match ($this->resource()) {
            null => $this->getPreparedJsonResponse($this->assessments->listAssessments($anr, $storyUuid)),
            'treatments' => $this->getPreparedJsonResponse($this->assessments->listTreatments($anr, $storyUuid)),
            'monitoring' => $this->getPreparedJsonResponse($this->assessments->listMonitoring($anr, $storyUuid)),
            'acceptances' => $this->getPreparedJsonResponse($this->assessments->listAcceptances($anr, $storyUuid)),
            default => $this->notFound(),
        };
    }

    public function create($data)
    {
        $this->capability->assertEnabled();
        $anr = $this->scenarioAnr();
        if ($anr === null || !is_array($data)) {
            return $this->invalidPayload();
        }
        $revision = $this->ifMatchRevision();
        if ($revision === null) {
            return $this->revisionConflict();
        }

        try {
            if ($this->kind() === 'profiles') {
                if ($this->resource() === 'assign' && $this->isUuid((string) $this->params()->fromRoute('id'))) {
                    $result = $this->assessments->assignProfile(
                        $anr,
                        (string) $this->params()->fromRoute('id'),
                        $revision,
                        $this->correlationId()
                    );
                } else {
                    $this->assertProfilePayload($data);
                    $result = $this->assessments->createProfileVersion($anr, $data, $revision, $this->correlationId());
                }
            } else {
                $storyUuid = (string) $this->params()->fromRoute('id');
                if ($this->stories->get($anr, $storyUuid) === null) {
                    return $this->notFound();
                }
                $result = match ($this->resource()) {
                    null => $this->assessments->assess(
                        $anr,
                        $storyUuid,
                        $this->assessmentPayload($data),
                        $revision,
                        $this->correlationId()
                    ),
                    'treatments' => $this->assessments->createTreatment(
                        $anr,
                        $storyUuid,
                        $this->treatmentPayload($data),
                        $revision,
                        $this->correlationId()
                    ),
                    'monitoring' => $this->assessments->createMonitoring(
                        $anr,
                        $storyUuid,
                        $this->monitoringPayload($data),
                        $revision,
                        $this->correlationId()
                    ),
                    'acceptances' => $this->assessments->acceptResidualRisk(
                        $anr,
                        $storyUuid,
                        $this->acceptancePayload($data),
                        $revision,
                        $this->correlationId()
                    ),
                    default => null,
                };
            }
        } catch (ActionForbiddenException $exception) {
            return $this->error(403, 'forbidden', $exception->getMessage());
        } catch (CoreException $exception) {
            return $this->error(
                $exception->getCode() === 403 ? 403 : 422,
                $exception->getCode() === 403 ? 'forbidden' : 'invalid_payload',
                $exception->getMessage()
            );
        } catch (\InvalidArgumentException $exception) {
            return $this->error(422, 'invalid_payload', $exception->getMessage());
        }

        return $result === null
            ? $this->revisionConflict()
            : $this->getSuccessfulJsonResponse($result);
    }

    /** @param array<string, mixed> $data */
    private function assertProfilePayload(array $data): void
    {
        $required = [
            'identifier',
            'title',
            'likelihoodLabels',
            'consequenceLabels',
            'dimensions',
            'aggregationPolicy',
            'bands',
            'acceptanceRules',
        ];
        if (array_diff($required, array_keys($data)) !== [] || array_diff(array_keys($data), $required) !== []) {
            throw new \InvalidArgumentException('The criteria-profile payload is invalid.');
        }
        if (!preg_match('/^[a-z0-9][a-z0-9-]{0,99}$/', (string) $data['identifier'])
            || trim((string) $data['title']) === '') {
            throw new \InvalidArgumentException('The criteria-profile identifier or title is invalid.');
        }
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function assessmentPayload(array $data): array
    {
        $allowed = [
            'assessmentType',
            'profileVersionUuid',
            'likelihood',
            'dimensionSeverities',
            'controlReferences',
            'evidenceReferences',
            'rationale',
            'legacyComparison',
        ];
        $this->assertOnly($data, $allowed, [
            'assessmentType',
            'profileVersionUuid',
            'likelihood',
            'dimensionSeverities',
            'rationale',
        ]);
        if (!$this->isUuid((string) $data['profileVersionUuid'])
            || !is_int($data['likelihood']) && !ctype_digit((string) $data['likelihood'])
            || !is_array($data['dimensionSeverities'])
            || trim((string) $data['rationale']) === '') {
            throw new \InvalidArgumentException('The assessment payload is invalid.');
        }

        return $data;
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function treatmentPayload(array $data): array
    {
        $this->assertOnly(
            $data,
            ['treatmentType', 'actionText', 'ownerId', 'dueAt', 'status'],
            ['treatmentType', 'actionText', 'status']
        );
        if (!in_array($data['treatmentType'], ['avoid', 'modify', 'share', 'retain'], true)
            || trim((string) $data['actionText']) === ''
            || !in_array($data['status'], ['planned', 'in_progress', 'completed', 'cancelled'], true)) {
            throw new \InvalidArgumentException('The treatment payload is invalid.');
        }

        return $data;
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function monitoringPayload(array $data): array
    {
        $this->assertOnly(
            $data,
            ['indicator', 'reassessmentTrigger', 'observation'],
            ['indicator', 'reassessmentTrigger']
        );
        if (trim((string) $data['indicator']) === '' || trim((string) $data['reassessmentTrigger']) === '') {
            throw new \InvalidArgumentException('The monitoring payload is invalid.');
        }

        return $data;
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function acceptancePayload(array $data): array
    {
        $this->assertOnly(
            $data,
            ['assessmentUuid', 'assignedSupervisorId', 'decision', 'reason'],
            ['assessmentUuid', 'assignedSupervisorId', 'decision', 'reason']
        );
        if (!$this->isUuid((string) $data['assessmentUuid'])
            || !filter_var($data['assignedSupervisorId'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
            || !in_array($data['decision'], ['accepted', 'not_accepted'], true)
            || trim((string) $data['reason']) === '') {
            throw new \InvalidArgumentException('The residual-risk acceptance payload is invalid.');
        }

        return $data;
    }

    /** @param array<string, mixed> $data @param array<int, string> $allowed @param array<int, string> $required */
    private function assertOnly(array $data, array $allowed, array $required): void
    {
        if (array_diff(array_keys($data), $allowed) !== [] || array_diff($required, array_keys($data)) !== []) {
            throw new \InvalidArgumentException('The Scenario request payload is invalid.');
        }
    }

    private function kind(): string
    {
        return (string) $this->params()->fromRoute('scenarioAssessmentKind');
    }

    private function resource(): ?string
    {
        $resource = $this->params()->fromRoute('resource');

        return is_string($resource) && $resource !== '' ? $resource : null;
    }

    private function ifMatchRevision(): ?int
    {
        $header = $this->getRequest()->getHeader('If-Match');
        $revision = $header === false ? false : filter_var(
            $header->getFieldValue(),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 0]]
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

    private function revisionConflict(): JsonResponse
    {
        return $this->error(409, 'revision_conflict', 'Reload the Scenario record before retrying.');
    }

    private function invalidPayload(): JsonResponse
    {
        return $this->error(422, 'invalid_payload', 'The Scenario request payload is invalid.');
    }

    private function notFound(): JsonResponse
    {
        return $this->error(404, 'not_found', 'Scenario analysis or risk story was not found.');
    }

    private function error(int $status, string $code, string $message): JsonResponse
    {
        return new JsonResponse(['error' => ['code' => $code, 'message' => $message]], $status);
    }
}
