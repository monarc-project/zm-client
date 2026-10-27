<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Scenario\Controller;

use Laminas\Diactoros\Response\JsonResponse;
use Monarc\Core\Controller\Handler\AbstractRestfulControllerRequestHandler;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\Core\Exception\ActionForbiddenException;
use Monarc\Core\Scenario\Feature\ScenarioCapability;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Scenario\Exception\ScenarioRiskScenarioException;
use Monarc\FrontOffice\Scenario\Service\ScenarioTemplateModelRiskScenarioCreationService;
use Monarc\FrontOffice\Scenario\Validator\ScenarioTemplateModelRiskScenarioCreationValidator;

/** Fixed, explicit creation endpoint; model and event provenance stays server-derived. */
final class ApiScenarioTemplateModelRiskScenarioController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;

    public function __construct(
        private ScenarioCapability $capability,
        private ScenarioTemplateModelRiskScenarioCreationService $service,
        private ScenarioTemplateModelRiskScenarioCreationValidator $validator
    ) {
    }

    public function create($data)
    {
        $this->capability->assertEnabled();
        $anr = $this->scenarioAnr();
        if ($anr === null) {
            return $this->notFound();
        }
        $revision = $this->ifMatchRevision();
        if ($revision === null) {
            return $this->error(409, 'revision_conflict', 'Reload the template model before creating local risk scenarios.');
        }
        try {
            return $this->getSuccessfulJsonResponse($this->service->create(
                $anr,
                $this->validator->validate($data),
                $revision,
                $this->correlationId()
            ));
        } catch (ActionForbiddenException $exception) {
            return $this->error(403, 'forbidden', $exception->getMessage());
        } catch (\InvalidArgumentException $exception) {
            return $this->error(422, 'validation_error', $exception->getMessage());
        } catch (ScenarioRiskScenarioException $exception) {
            return $this->error(
                in_array($exception->reason(), ['revision_conflict', 'idempotency_conflict'], true) ? 409 : 422,
                $exception->reason(),
                $exception->getMessage()
            );
        }
    }

    private function scenarioAnr(): ?Anr
    {
        $anr = $this->getRequest()->getAttribute('anr');

        return $anr instanceof Anr && $anr->getAnalysisType() === Anr::ANALYSIS_TYPE_SCENARIO ? $anr : null;
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

    private function correlationId(): string
    {
        $header = $this->getRequest()->getHeader('X-Correlation-ID');

        return $header === false ? bin2hex(random_bytes(16)) : substr($header->getFieldValue(), 0, 128);
    }

    private function notFound(): JsonResponse
    {
        return $this->error(404, 'not_found', 'Scenario analysis was not found.');
    }

    private function error(int $status, string $code, string $message): JsonResponse
    {
        return new JsonResponse(['error' => ['code' => $code, 'message' => $message]], $status);
    }
}
