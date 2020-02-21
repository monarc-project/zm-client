<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\FrontOffice\Service\ClientService;
use Monarc\Core\Controller\AbstractController;
use Laminas\View\Model\JsonModel;

/**
 * Api Clients Controller
 *
 * Class ApiClientsController
 * @package Monarc\FrontOffice\Controller
 */
class ApiClientsController extends AbstractController
{
    protected $name = 'clients';

    /**
     * @inheritdoc
     */
    public function create($data)
    {
        /** @var ClientService $service */
        $service = $this->getService();

        // Security: Don't allow changing role, password, status and history fields. To clean later.
        if (isset($data['id'])) unset($data['id']);
        if (isset($data['updatedAt'])) unset($data['updatedAt']);
        if (isset($data['updater'])) unset($data['updater']);
        if (isset($data['createdAt'])) unset($data['createdAt']);
        if (isset($data['creator'])) unset($data['creator']);

        $service->create($data);

        return new JsonModel(['status' => 'ok']);
    }

    /**
     * @inheritdoc
     */
    public function update($id, $data)
    {
        /** @var ClientService $service */
        $service = $this->getService();

        // Security: Don't allow changing role, password, status and history fields. To clean later.
        if (isset($data['updatedAt'])) unset($data['updatedAt']);
        if (isset($data['updater'])) unset($data['updater']);
        if (isset($data['createdAt'])) unset($data['createdAt']);
        if (isset($data['creator'])) unset($data['creator']);

        $service->update($id, $data);

        return new JsonModel(['status' => 'ok']);
    }
}
