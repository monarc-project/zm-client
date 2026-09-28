<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Scenario\Controller;

use Laminas\Diactoros\Response\JsonResponse;
use Monarc\Core\Controller\Handler\AbstractRestfulControllerRequestHandler;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\Core\Scenario\Feature\ScenarioCapability;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Scenario\Table\ScenarioReferenceTable;

/** Lists a small authorised projection for risk-story reference pickers. */
final class ApiScenarioRiskStoryReferenceController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;

    public function __construct(private ScenarioCapability $capability, private ScenarioReferenceTable $references)
    {
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
        $allowed = [
            'risk-source', 'threat', 'vulnerability', 'asset', 'process',
            'object', 'information', 'supplier', 'objective', 'control',
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
            min(100, max(1, (int) $this->params()->fromQuery('pageSize', 25)))
        ));
    }
}
