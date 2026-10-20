<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Scenario\Controller;

use Laminas\Diactoros\Response\JsonResponse;
use Monarc\Core\Controller\Handler\AbstractRestfulControllerRequestHandler;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\Core\Scenario\Feature\ScenarioCapability;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Scenario\Service\ScenarioRiskRegisterService;
use Monarc\FrontOffice\Scenario\Validator\ScenarioRiskRegisterQuery;

/** Exposes the bounded, read-only, authorised Scenario risk-register projection. */
final class ApiScenarioRiskRegisterController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;

    public function __construct(private ScenarioCapability $capability, private ScenarioRiskRegisterService $register)
    {
    }

    public function getList()
    {
        $this->capability->assertEnabled();
        $anr = $this->getRequest()->getAttribute('anr');
        if (!$anr instanceof Anr || $anr->getAnalysisType() !== Anr::ANALYSIS_TYPE_SCENARIO) {
            return new JsonResponse(['error' => ['code' => 'not_found', 'message' => 'Scenario register was not found.']], 404);
        }
        try {
            return $this->getPreparedJsonResponse($this->register->list($anr, ScenarioRiskRegisterQuery::validate($this->params()->fromQuery())));
        } catch (\InvalidArgumentException $exception) {
            return new JsonResponse(['error' => ['code' => 'invalid_query', 'message' => $exception->getMessage()]], 422);
        }
    }
}
