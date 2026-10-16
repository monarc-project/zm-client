<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Scenario\Controller;

use Laminas\Diactoros\Response\JsonResponse;
use Monarc\Core\Controller\Handler\AbstractRestfulControllerRequestHandler;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\Core\Scenario\Feature\ScenarioCapability;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Scenario\Service\ScenarioMethodContextService;
use Monarc\FrontOffice\Validator\InputValidator\ReassessmentTrigger\PatchReassessmentTriggerDataInputValidator;
use Monarc\FrontOffice\Validator\InputValidator\ReassessmentTrigger\PostReassessmentTriggerDataInputValidator;

/** Exposes the established MONARC context-method data inside an authorised Scenario ANR. */
final class ApiScenarioMethodContextController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;

    public function __construct(
        private ScenarioCapability $capability,
        private ScenarioMethodContextService $methodContextService,
        private PostReassessmentTriggerDataInputValidator $postReassessmentTriggerValidator,
        private PatchReassessmentTriggerDataInputValidator $patchReassessmentTriggerValidator
    ) {
    }

    public function getList()
    {
        $anr = $this->scenarioAnr();
        if ($anr === null) {
            return $this->notFound();
        }

        return match ($this->resource()) {
            'trends' => $this->getPreparedJsonResponse([
                'items' => $this->methodContextService->trendQuestions($anr),
            ]),
            'reassessment' => $this->getPreparedJsonResponse(
                $this->methodContextService->reassessmentStrategy($anr)
            ),
            'reassessment-triggers' => $this->getPreparedJsonResponse([
                'items' => $this->methodContextService->reassessmentStrategy($anr)['triggers'],
            ]),
            default => $this->notFound(),
        };
    }

    public function create($data)
    {
        $anr = $this->scenarioAnr();
        if ($anr === null || $this->resource() !== 'reassessment-triggers') {
            return $this->notFound();
        }
        $this->validatePostParams($this->postReassessmentTriggerValidator, $data);

        return $this->getSuccessfulJsonResponse($this->methodContextService->createReassessmentTrigger(
            $anr,
            $this->postReassessmentTriggerValidator->getValidData(),
            $this->correlationId()
        ));
    }

    public function patch($id, $data)
    {
        $anr = $this->scenarioAnr();
        if ($anr === null) {
            return $this->notFound();
        }

        try {
            return match ($this->resource()) {
                'trends' => $this->patchTrend($anr, $id, $data),
                'reassessment-triggers' => $this->patchTrigger($anr, $id, $data),
                default => $this->notFound(),
            };
        } catch (\InvalidArgumentException $exception) {
            return $this->invalid($exception->getMessage());
        }
    }

    public function patchList($data)
    {
        $anr = $this->scenarioAnr();
        if ($anr === null || $this->resource() !== 'reassessment') {
            return $this->notFound();
        }

        try {
            return $this->patchReassessment($anr, $data);
        } catch (\InvalidArgumentException $exception) {
            return $this->invalid($exception->getMessage());
        }
    }

    public function delete($id)
    {
        $anr = $this->scenarioAnr();
        if ($anr === null || $this->resource() !== 'reassessment-triggers' || !ctype_digit((string) $id)) {
            return $this->notFound();
        }
        try {
            $this->methodContextService->deleteReassessmentTrigger($anr, (int) $id, $this->correlationId());
        } catch (\InvalidArgumentException $exception) {
            return $this->invalid($exception->getMessage());
        }

        return $this->getSuccessfulJsonResponse();
    }

    private function patchTrend(Anr $anr, mixed $id, array $data): JsonResponse
    {
        if (!ctype_digit((string) $id) || array_keys($data) !== ['response']) {
            return $this->invalid('The trend response is invalid.');
        }

        return $this->getSuccessfulJsonResponse($this->methodContextService->updateTrendResponse(
            $anr,
            (int) $id,
            $data['response'],
            $this->correlationId()
        ));
    }

    private function patchReassessment(Anr $anr, array $data): JsonResponse
    {
        $allowed = ['lastReviewAt', 'reviewFrequency'];
        if ($data === [] || array_diff(array_keys($data), $allowed) !== []
            || (isset($data['lastReviewAt']) && !is_string($data['lastReviewAt']))
            || (isset($data['reviewFrequency']) && !is_string($data['reviewFrequency']))) {
            return $this->invalid('The reassessment strategy is invalid.');
        }

        return $this->getSuccessfulJsonResponse($this->methodContextService->updateReassessmentStrategy(
            $anr,
            $data,
            $this->correlationId()
        ));
    }

    private function patchTrigger(Anr $anr, mixed $id, array $data): JsonResponse
    {
        if (!ctype_digit((string) $id)) {
            return $this->notFound();
        }
        $this->validatePostParams($this->patchReassessmentTriggerValidator, $data);

        return $this->getSuccessfulJsonResponse($this->methodContextService->updateReassessmentTrigger(
            $anr,
            (int) $id,
            $this->patchReassessmentTriggerValidator->getValidData(),
            $this->correlationId()
        ));
    }

    private function scenarioAnr(): ?Anr
    {
        $this->capability->assertEnabled();
        $anr = $this->getRequest()->getAttribute('anr');

        return $anr instanceof Anr && $anr->getAnalysisType() === Anr::ANALYSIS_TYPE_SCENARIO ? $anr : null;
    }

    private function resource(): string
    {
        return (string) $this->params()->fromRoute('resource');
    }

    private function correlationId(): string
    {
        $header = $this->getRequest()->getHeader('X-Correlation-ID');

        return $header === false ? bin2hex(random_bytes(16)) : $header->getFieldValue();
    }

    private function invalid(string $message): JsonResponse
    {
        return new JsonResponse(['error' => ['code' => 'invalid_payload', 'message' => $message]], 422);
    }

    private function notFound(): JsonResponse
    {
        return new JsonResponse([
            'error' => ['code' => 'not_found', 'message' => 'Scenario method context was not found.'],
        ], 404);
    }
}
