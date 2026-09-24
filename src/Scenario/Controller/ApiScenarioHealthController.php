<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Scenario\Controller;

use Laminas\Mvc\Controller\AbstractRestfulController;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\Core\Scenario\Contract\ScenarioStatus;
use Monarc\Core\Scenario\Feature\ScenarioCapability;

final class ApiScenarioHealthController extends AbstractRestfulController
{
    use ControllerRequestResponseHandlerTrait;

    public function __construct(private ScenarioCapability $scenarioCapability)
    {
    }

    public function getList()
    {
        return $this->getPreparedJsonResponse(ScenarioStatus::health($this->scenarioCapability->isEnabled()));
    }
}
