<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Scenario\Controller;

use Laminas\Mvc\Controller\AbstractRestfulController;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\Core\Scenario\Contract\ScenarioStatus;
use Monarc\Core\Scenario\Feature\ScenarioCapability;
use Monarc\FrontOffice\Scenario\Service\ScenarioReadinessService;

final class ApiScenarioReadyController extends AbstractRestfulController
{
    use ControllerRequestResponseHandlerTrait;

    public function __construct(
        private ScenarioCapability $scenarioCapability,
        private ScenarioReadinessService $readinessService
    ) {
    }

    public function getList()
    {
        $ready = $this->readinessService->isReady();
        if (!$ready) {
            $this->getResponse()->setStatusCode(503);
        }

        $status = ScenarioStatus::readiness($this->scenarioCapability->isEnabled(), $ready);

        return $this->getPreparedJsonResponse($status);
    }
}
