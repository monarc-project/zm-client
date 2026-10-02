<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Scenario\Controller;

use Laminas\Diactoros\Response\JsonResponse;
use Monarc\Core\Controller\Handler\AbstractRestfulControllerRequestHandler;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\Core\Scenario\Feature\ScenarioCapability;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Scenario\Table\ScenarioReferenceTable;
use Monarc\FrontOffice\Service\RiskSourceService;
use Monarc\FrontOffice\Validator\InputValidator\RiskSource\PostRiskSourceDataInputValidator;

/** Lists and creates a narrow, authorised contextual reference projection. */
final class ApiScenarioRiskScenarioReferenceController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;

    public function __construct(
        private ScenarioCapability $capability,
        private ScenarioReferenceTable $references,
        private RiskSourceService $riskSources,
        private PostRiskSourceDataInputValidator $riskSourceValidator
    ) {
    }

    public function getList()
    {
        $this->capability->assertEnabled();
        $anr = $this->getRequest()->getAttribute('anr');
        if (!$anr instanceof Anr || $anr->getAnalysisType() !== Anr::ANALYSIS_TYPE_SCENARIO) {
            return new JsonResponse([
                'error' => ['code' => 'not_found', 'message' => 'Scenario analysis was not found.'],
            ], 404);
        }

        $kind = (string) $this->params()->fromQuery('kind', '');
        // The Scenario workspace deliberately presents only sources that have
        // a truthful, server-verifiable meaning in the current ANR.  Legacy
        // object and risk-register projections remain readable through their
        // existing APIs, but must never be relabelled as Scenario context.
        $allowed = [
            'asset', 'asset-type', 'threat', 'vulnerability', 'referential',
            'control', 'recommendation-set', 'recommendation', 'risk-source',
            // Legacy subject links used a generic object record. This is a
            // read-only compatibility lookup; new links use Asset library.
            'object',
        ];
        if (!in_array($kind, $allowed, true)) {
            return new JsonResponse([
                'error' => ['code' => 'invalid_kind', 'message' => 'The reference kind is invalid.'],
            ], 422);
        }

        return $this->getPreparedJsonResponse($this->references->list(
            $anr,
            $kind,
            mb_substr(trim((string) $this->params()->fromQuery('query', '')), 0, 100),
            max(1, (int) $this->params()->fromQuery('page', 1)),
            min(100, max(1, (int) $this->params()->fromQuery('pageSize', 25))),
            trim((string) $this->params()->fromQuery('parent', ''))
        ));
    }

    public function create($data)
    {
        $this->capability->assertEnabled();
        $anr = $this->scenarioAnr();
        if ($anr === null) {
            return new JsonResponse([
                'error' => ['code' => 'not_found', 'message' => 'Scenario analysis was not found.'],
            ], 404);
        }
        if (!is_array($data)
            || count($data) !== 2
            || array_diff(array_keys($data), ['kind', 'label']) !== []
            || $data['kind'] !== 'risk-source') {
            return new JsonResponse([
                'error' => ['code' => 'invalid_payload', 'message' => 'The contextual reference payload is invalid.'],
            ], 422);
        }

        $this->validatePostParams($this->riskSourceValidator, ['label' => $data['label']]);
        $riskSource = $this->riskSources->create($anr, $this->riskSourceValidator->getValidData());

        return $this->getSuccessfulJsonResponse([
            'value' => (string) $riskSource->getId(),
            'label' => $riskSource->getLabel(),
            'type' => 'risk-source',
            'available' => true,
        ]);
    }

    private function scenarioAnr(): ?Anr
    {
        $anr = $this->getRequest()->getAttribute('anr');

        return $anr instanceof Anr && $anr->getAnalysisType() === Anr::ANALYSIS_TYPE_SCENARIO ? $anr : null;
    }
}
