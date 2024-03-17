<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\Core\Controller\Handler\AbstractRestfulControllerRequestHandler;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Service\InstanceRiskOwnerService;

class ApiAnrRiskOwnersController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;

    private InstanceRiskOwnerService $instanceRiskOwnerService;

    public function __construct(InstanceRiskOwnerService $instanceRiskOwnerService)
    {
        $this->instanceRiskOwnerService = $instanceRiskOwnerService;
    }

    public function getList()
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        $instanceRiskOwners = $this->instanceRiskOwnerService->getList($anr, $this->prepareParams());

        return $this->getPreparedJsonResponse([
            'instanceRiskOwners' => $instanceRiskOwners,
            'count' => \count($instanceRiskOwners),
        ]);
    }

    protected function prepareParams(): array
    {
        return [
            'name' => $this->params()->fromQuery('filter', ''),
        ];
    }
}
