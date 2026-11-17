<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2026 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Scenario\Controller;

use Laminas\Diactoros\Response\JsonResponse;
use Monarc\Core\Controller\Handler\AbstractRestfulControllerRequestHandler;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\Core\Exception\ActionForbiddenException;
use Monarc\Core\Scenario\Feature\ScenarioCapability;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Scenario\Service\ScenarioTemplatePackageService;

final class ApiScenarioTemplatePackageController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;

    public function __construct(private ScenarioCapability $capability, private ScenarioTemplatePackageService $service)
    {
    }

    public function getList()
    {
        $this->capability->assertEnabled();
        $anr = $this->getRequest()->getAttribute('anr');
        if (!$anr instanceof Anr || $anr->getAnalysisType() !== Anr::ANALYSIS_TYPE_SCENARIO) {
            return new JsonResponse(['error' => ['code' => 'not_found']], 404);
        }
        try {
            return $this->getPreparedJsonResponse($this->service->read($anr));
        } catch (ActionForbiddenException $exception) {
            return new JsonResponse(['error' => ['code' => 'forbidden', 'message' => $exception->getMessage()]], 403);
        }
    }
}
