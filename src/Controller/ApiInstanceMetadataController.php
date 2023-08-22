<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2023 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Laminas\View\Model\JsonModel;
use Monarc\Core\Controller\Handler\AbstractRestfulControllerRequestHandler;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\FrontOffice\Model\Entity\Anr;
use Monarc\FrontOffice\Service\InstanceMetadataService;

class ApiInstanceMetadataController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;

    private InstanceMetadataService $instanceMetadataService;

    public function __construct(InstanceMetadataService $instanceMetadataService)
    {
        $this->instanceMetadataService = $instanceMetadataService;
    }

    public function getList()
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');
        $instanceId = (int)$this->params()->fromRoute('instanceid');

        return new JsonModel([
            'data' => $this->instanceMetadataService->getInstancesMetadata($anr, $instanceId),
        ]);
    }

    /**
     * @param array $data
     */
    public function create($data)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');
        $instanceId = (int)$this->params()->fromRoute('instanceid');

        return $this->getSuccessfulJsonResponse([
            'id' => $this->instanceMetadataService->create($anr, $instanceId, $data)->getId(),
        ]);
    }

    /**
     * @param array $data
     */
    public function update($id, $data)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        $this->instanceMetadataService->update($anr, (int)$id, $data);

        return $this->getSuccessfulJsonResponse();
    }
}
