<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Scenario\Controller;

use Laminas\Mvc\Controller\AbstractRestfulController;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\FrontOffice\Scenario\Service\LegacyBridgeService;

/** Handles only request extraction; bridge policy belongs to LegacyBridgeService. */
final class ApiScenarioAuthBridgeController extends AbstractRestfulController
{
    use ControllerRequestResponseHandlerTrait;

    public function __construct(private LegacyBridgeService $bridge)
    {
    }

    public function create($data)
    {
        try {
            return $this->getPreparedJsonResponse($this->bridge->handle(
                $this->getRequest()->getUri()->getPath(),
                $data,
                $this->getHeaderValue('token'),
                $this->getHeaderValue('x-scenario-service-token')
            ));
        } catch (\Throwable) {
            $this->getResponse()->setStatusCode(401);

            return $this->getPreparedJsonResponse([
                'error' => ['code' => 'invalid_handoff', 'message' => 'Invalid Scenario handoff'],
            ]);
        }
    }

    private function getHeaderValue(string $name): ?string
    {
        $header = $this->getRequest()->getHeader($name);

        return $header ? $header->getFieldValue() : null;
    }
}
