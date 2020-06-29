<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\View\Model\JsonModel;
use Monarc\FrontOffice\Service\ClientService;

/**
 * Api Clients Controller
 *
 * Class ApiClientsController
 * @package Monarc\FrontOffice\Controller
 */
class ApiClientsController extends AbstractRestfulController
{
    /** @var ClientService */
    private $clientService;

    public function __construct(ClientService $clientService)
    {
        $this->clientService = $clientService;
    }

    public function getList()
    {
        return new JsonModel($this->clientService->getAll());
    }

    public function patch($id, $data)
    {
        $this->clientService->patch((int)$id, $data);

        return new JsonModel(array('status' => 'ok'));
    }
}
