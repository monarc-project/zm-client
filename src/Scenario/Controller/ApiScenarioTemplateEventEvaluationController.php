<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Scenario\Controller;

use Laminas\Diactoros\Response\JsonResponse;
use Monarc\Core\Controller\Handler\AbstractRestfulControllerRequestHandler;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\Core\Exception\ActionForbiddenException;
use Monarc\Core\Scenario\Feature\ScenarioCapability;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Scenario\Service\ScenarioTemplateEventEvaluationService;
use Monarc\FrontOffice\Scenario\Validator\ScenarioTemplateEventEvaluationValidator;

final class ApiScenarioTemplateEventEvaluationController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;

    public function __construct(
        private ScenarioCapability $capability,
        private ScenarioTemplateEventEvaluationService $service,
        private ScenarioTemplateEventEvaluationValidator $validator
    ) {
    }

    public function getList()
    {
        $this->capability->assertEnabled();
        $anr = $this->scenarioAnr();

        return $anr === null ? $this->notFound() : $this->getPreparedJsonResponse($this->service->list($anr));
    }

    public function patch($id, $data)
    {
        $this->capability->assertEnabled();
        $anr = $this->scenarioAnr();
        if ($anr === null) {
            return $this->notFound();
        }
        if (!is_array($data) || array_diff(array_keys($data), ['state', 'comment']) !== []) {
            return $this->error(422, 'validation_error', 'Invalid template event evaluation payload.');
        }
        $this->validatePostParams($this->validator, $data);
        $revision = $this->ifMatchRevision();
        if ($revision === null) {
            return $this->error(409, 'revision_conflict', 'Reload the event evaluation before retrying.');
        }
        try {
            return $this->getSuccessfulJsonResponse($this->service->save(
                $anr,
                (string) $id,
                $this->validator->getValidData(),
                $revision,
                $this->correlationId()
            ));
        } catch (ActionForbiddenException $exception) {
            return $this->error(403, 'forbidden', $exception->getMessage());
        } catch (\RuntimeException $exception) {
            $status = str_contains($exception->getMessage(), 'conflict') ? 409 : 404;

            return $this->error($status, $status === 409 ? 'revision_conflict' : 'not_found', $exception->getMessage());
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
            ['options' => ['min_range' => 0]]
        );

        return $revision === false ? null : $revision;
    }

    private function correlationId(): string
    {
        $header = $this->getRequest()->getHeader('X-Correlation-ID');

        return $header === false ? bin2hex(random_bytes(16)) : $header->getFieldValue();
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
