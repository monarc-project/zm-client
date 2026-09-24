<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Scenario\Controller;

use Laminas\Diactoros\Response\JsonResponse;
use Monarc\Core\Controller\Handler\AbstractRestfulControllerRequestHandler;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\Core\Scenario\Feature\ScenarioCapability;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Scenario\Service\ScenarioAnalysisService;
use Monarc\FrontOffice\Scenario\Service\ScenarioTemplateInstantiationService;
use Monarc\FrontOffice\Scenario\Validator\ScenarioAnalysisCreateValidator;
use Monarc\FrontOffice\Scenario\Validator\ScenarioAnalysisUpdateValidator;
use Monarc\FrontOffice\Scenario\Validator\ScenarioTemplateInstantiationValidator;
use Monarc\FrontOffice\Scenario\Validator\ScenarioTemplateOverrideValidator;

final class ApiScenarioAnalysisController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;

    public function __construct(
        private ScenarioCapability $capability,
        private ScenarioAnalysisService $scenarioAnalysisService,
        private ScenarioAnalysisCreateValidator $createValidator,
        private ScenarioAnalysisUpdateValidator $updateValidator,
        private ScenarioTemplateInstantiationService $templateInstantiationService,
        private ScenarioTemplateInstantiationValidator $templateInstantiationValidator,
        private ScenarioTemplateOverrideValidator $templateOverrideValidator
    ) {
    }

    public function getList()
    {
        $this->capability->assertEnabled();
        $anr = $this->scenarioAnr();
        if ($anr === null) {
            return $this->notFound();
        }
        if ($this->params()->fromRoute('scenarioAction') === 'template') {
            $template = $this->templateInstantiationService->getSnapshot($anr);

            return $template === null
                ? $this->notFound()
                : $this->getPreparedJsonResponse($template);
        }

        $analysis = $this->scenarioAnalysisService->get($anr);

        return $analysis === null ? $this->notFound() : $this->getPreparedJsonResponse($analysis);
    }

    public function create($data)
    {
        $this->capability->assertEnabled();
        $anr = $this->scenarioAnr();
        if ($anr === null) {
            return $this->notFound();
        }
        if ($this->params()->fromRoute('scenarioAction') === 'template') {
            return $this->instantiateTemplate($anr, $data);
        }
        $this->validatePostParams($this->createValidator, $data);
        $current = $this->scenarioAnalysisService->get($anr);
        if ($current !== null) {
            return $this->error(
                409,
                'already_exists',
                'Reload the existing scenario analysis.'
            );
        }
        return $this->getSuccessfulJsonResponse($this->scenarioAnalysisService->create(
            $anr,
            $this->createValidator->getValidData(),
            $this->correlationId()
        ));
    }

    private function instantiateTemplate(Anr $anr, array $data): JsonResponse
    {
        $this->validatePostParams($this->templateInstantiationValidator, $data);
        $header = $this->getRequest()->getHeader('If-Match');
        $revision = $header === false ? null : filter_var(
            $header->getFieldValue(),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );
        $result = $this->templateInstantiationService->instantiate(
            $anr,
            $this->templateInstantiationValidator->getValidData(),
            $revision === false ? null : $revision,
            $this->correlationId()
        );
        if ($result === null) {
            return $this->error(409, 'revision_conflict', 'Reload the scenario analysis before retrying.');
        }

        return $this->getSuccessfulJsonResponse($result);
    }

    public function patch($id, $data)
    {
        $this->capability->assertEnabled();
        $anr = $this->scenarioAnr();
        if ($anr === null) {
            return $this->notFound();
        }
        if ($this->params()->fromRoute('scenarioAction') === 'template') {
            return $this->saveTemplateOverride($anr, (string) $id, $data);
        }
        $current = $this->scenarioAnalysisService->get($anr);
        if ($current === null || $current['uuid'] !== $id) {
            return $this->notFound();
        }
        $this->validatePostParams($this->updateValidator, $data);
        $ifMatch = $this->getRequest()->getHeader('If-Match');
        $revision = $ifMatch === false
            ? false
            : filter_var($ifMatch->getFieldValue(), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $updated = $revision !== false ? $this->scenarioAnalysisService->update(
            $anr,
            (string) $id,
            $this->updateValidator->getValidData(),
            $revision,
            $this->correlationId()
        ) : null;
        if ($updated === null) {
            return $this->error(
                409,
                'revision_conflict',
                'Reload the scenario analysis before retrying.'
            );
        }

        return $this->getSuccessfulJsonResponse($updated);
    }

    private function saveTemplateOverride(Anr $anr, string $recordUuid, array $data): JsonResponse
    {
        $this->validatePostParams($this->templateOverrideValidator, $data);
        $ifMatch = $this->getRequest()->getHeader('If-Match');
        $revision = $ifMatch === false
            ? false
            : filter_var($ifMatch->getFieldValue(), FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        if ($revision === false) {
            return $this->error(
                409,
                'revision_conflict',
                'Reload the template snapshot before retrying.'
            );
        }
        $result = $this->templateInstantiationService->saveOverride(
            $anr,
            $recordUuid,
            $this->templateOverrideValidator->getValidData(),
            $revision,
            $this->correlationId()
        );
        if ($result === null) {
            return $this->error(
                409,
                'revision_conflict',
                'Reload the template snapshot before retrying.'
            );
        }

        return $this->getSuccessfulJsonResponse($result);
    }

    private function scenarioAnr(): ?Anr
    {
        $anr = $this->getRequest()->getAttribute('anr');
        if (!$anr instanceof Anr
            || $anr->getAnalysisType() !== Anr::ANALYSIS_TYPE_SCENARIO
        ) {
            return null;
        }
        return $anr;
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

    private function error(int $statusCode, string $code, string $message): JsonResponse
    {
        return new JsonResponse([
            'error' => ['code' => $code, 'message' => $message],
        ], $statusCode);
    }
}
