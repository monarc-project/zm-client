<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Scenario\Controller;

use Laminas\Diactoros\Response\JsonResponse;
use Monarc\Core\Controller\Handler\AbstractRestfulControllerRequestHandler;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\Core\Exception\ActionForbiddenException;
use Monarc\Core\Scenario\Feature\ScenarioCapability;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Scenario\Service\ScenarioTreatmentService;
use Monarc\FrontOffice\Scenario\Validator\ScenarioTreatmentValidator;

/** Exposes narrowly scoped treatment-plan tracking beneath one authorised Scenario risk scenario. */
final class ApiScenarioTreatmentController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;

    public function __construct(
        private ScenarioCapability $capability,
        private ScenarioTreatmentService $treatments
    ) {
    }

    public function getList()
    {
        $this->capability->assertEnabled();
        $anr = $this->scenarioAnr();
        if ($anr === null) {
            return $this->notFound();
        }
        if ($this->action() === 'owners') {
            return $this->getPreparedJsonResponse($this->treatments->owners($anr));
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
        $scenarioUuid = $this->scenarioUuid();
        $treatmentUuid = (string) $id;
        if ($this->action() === 'history') {
            if ($this->treatments->get($anr, $scenarioUuid, $treatmentUuid) === null) {
                return $this->notFound();
            }

            return $this->getPreparedJsonResponse($this->treatments->history($anr, $scenarioUuid, $treatmentUuid));
        }
        if ($this->action() === 'recommendation') {
            $implementation = $this->treatments->getImplementation(
                $anr,
                $scenarioUuid,
                $treatmentUuid,
                (string) $this->params()->fromRoute('implementationId')
            );

            return $implementation === null ? $this->notFound() : $this->getPreparedJsonResponse($implementation);
        }
        $treatment = $this->treatments->get($anr, $scenarioUuid, $treatmentUuid);

        return $treatment === null ? $this->notFound() : $this->getPreparedJsonResponse($treatment);
    }

    public function patch($id, $data)
    {
        $this->capability->assertEnabled();
        $anr = $this->scenarioAnr();
        if ($anr === null) {
            return $this->notFound();
        }
        $riskScenarioRevision = $this->scenarioRevision();
        $treatmentRevision = $this->ifMatchRevision();
        $idempotencyKey = $this->idempotencyKey();
        if ($riskScenarioRevision === null || $treatmentRevision === null || $idempotencyKey === null) {
            return $this->invalidPayload();
        }

        try {
            if ($this->action() === 'recommendation') {
                $result = $this->treatments->updateImplementation(
                    $anr,
                    $this->scenarioUuid(),
                    (string) $id,
                    (string) $this->params()->fromRoute('implementationId'),
                    ScenarioTreatmentValidator::implementationUpdate($data),
                    $riskScenarioRevision,
                    $treatmentRevision,
                    $this->correlationId(),
                    $idempotencyKey
                );
            } elseif ($this->action() === 'treatment') {
                $result = $this->treatments->update(
                    $anr,
                    $this->scenarioUuid(),
                    (string) $id,
                    ScenarioTreatmentValidator::treatmentUpdate($data),
                    $riskScenarioRevision,
                    $treatmentRevision,
                    $this->correlationId(),
                    $idempotencyKey
                );
            } else {
                return $this->notFound();
            }
        } catch (ActionForbiddenException $exception) {
            return $this->error(403, 'forbidden', $exception->getMessage());
        } catch (\LogicException|\InvalidArgumentException $exception) {
            return $this->error(422, 'invalid_transition', $exception->getMessage());
        }

        return $result === null
            ? $this->revisionConflict()
            : $this->getSuccessfulJsonResponse($result);
    }

    private function action(): string
    {
        return (string) $this->params()->fromRoute('scenarioTreatmentAction');
    }

    private function scenarioUuid(): string
    {
        return (string) $this->params()->fromRoute('scenarioId');
    }

    private function ifMatchRevision(): ?int
    {
        $header = $this->getRequest()->getHeader('If-Match');
        $revision = $header === false
            ? false
            : filter_var($header->getFieldValue(), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $revision === false ? null : $revision;
    }

    private function scenarioRevision(): ?int
    {
        $header = $this->getRequest()->getHeader('X-Scenario-Revision');
        $revision = $header === false
            ? false
            : filter_var($header->getFieldValue(), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $revision === false ? null : $revision;
    }

    private function idempotencyKey(): ?string
    {
        $header = $this->getRequest()->getHeader('Idempotency-Key');
        $value = $header === false ? '' : $header->getFieldValue();

        return $this->isUuid($value) ? $value : null;
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
        return $this->error(409, 'revision_conflict', 'Reload the Scenario treatment before retrying.');
    }

    private function invalidPayload(): JsonResponse
    {
        return $this->error(422, 'invalid_payload', 'The Scenario treatment request is invalid.');
    }

    private function notFound(): JsonResponse
    {
        return $this->error(404, 'not_found', 'Scenario treatment or recommendation implementation was not found.');
    }

    private function error(int $status, string $code, string $message): JsonResponse
    {
        return new JsonResponse(['error' => ['code' => $code, 'message' => $message]], $status);
    }
}
