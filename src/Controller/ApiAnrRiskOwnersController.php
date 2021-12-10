<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2021 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\View\Model\JsonModel;
use Monarc\FrontOffice\Service\InstanceRiskOwnerService;

/**
 * Api ANR Risk Owners Controller
 *
 * Class ApiAnrRiskOwnersController
 * @package Monarc\FrontOffice\Controller
 */
class ApiAnrRiskOwnersController extends AbstractRestfulController
{
    private InstanceRiskOwnerService $instanceRiskOwnerService;

    public function __construct(InstanceRiskOwnerService $instanceRiskOwnerService)
    {
        $this->instanceRiskOwnerService = $instanceRiskOwnerService;
    }

    public function getList()
    {
        $anrId = (int)$this->params()->fromRoute('anrid');

        $instanceRiskOwners = $this->instanceRiskOwnerService->getList($anrId, $this->prepareParams());

        return new JsonModel([
            'instanceRiskOwners' => $instanceRiskOwners,
            'count' => \count($instanceRiskOwners),
        ]);
    }

    public function update($id, $data)
    {
        file_put_contents('php://stderr', print_r($data , TRUE).PHP_EOL);
        $anrId = (int)$this->params()->fromRoute('anrid');
        $data['anr'] = $anrId ;
        $instanceRiskOwnersId = $this->instanceRiskOwnerService->updateOwner($id, $data);
        return new JsonModel([
            'id' => $instanceRiskOwnersId,
            'status' => 'ok',
        ]);
    }

    public function delete($id)
    {
       // $anrId = (int)$this->params()->fromRoute('anrid');
        $this->instanceRiskOwnerService->deleteOwner((int)$id);
        return new JsonModel(['status' => 'ok']);
    }

    protected function prepareParams(): array
    {
        return [
            'name' => $this->params()->fromQuery('filter', ''),
        ];
    }
}
