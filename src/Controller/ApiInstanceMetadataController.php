<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2022 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\View\Model\JsonModel;
use Monarc\Core\Exception\Exception;
use Monarc\FrontOffice\Service\InstanceMetadataService;

/**
 * Api Anr Metadatas On Instances Controller
 *
 * Class ApiAnrMetadatasOnInstancesController
 * @package Monarc\FrontOffice\Controller
 */
class ApiInstanceMetadataController extends AbstractRestfulController
{

    private InstanceMetadataService $instanceMetadataService;

    public function __construct(InstanceMetadataService $instanceMetadataService)
    {
        $this->instanceMetadataService = $instanceMetadataService;
    }

    public function create($data)
    {
        $anrId = (int) $this->params()->fromRoute('anrid');
        return new JsonModel([
            'status' => 'ok',
            'id' => $this->instanceMetadataService->createInstanceMetadata($anrId, $data),
        ]);
    }

    public function getList()
    {
        $anrId = (int) $this->params()->fromRoute('anrid');
        $language = $this->params()->fromQuery("language");
        $instanceId = (int)$this->params()->fromRoute("instanceid");

        return new JsonModel([
            'data' => $this->instanceMetadataService->getInstancesMetadatas($anrId, $instanceId, $language),
        ]);
    }

    public function delete($id)
    {
        $this->instanceMetadataService->deleteInstanceMetadata($id);

        return new JsonModel(['status' => 'ok']);
    }

    public function get($id)
    {
        $anrId = (int) $this->params()->fromRoute('anrid');
        return new JsonModel([
            'data' => $this->instanceMetadataService->getInstanceMetadata($anrId, $id),
        ]);
    }

    public function update($id, $data)
    {
        if ($this->instanceMetadataService->update((int)$id, $data)) {
            return new JsonModel(['status' => 'ok']);
        }

        return new JsonModel(['status' => 'ko']);
    }
}
