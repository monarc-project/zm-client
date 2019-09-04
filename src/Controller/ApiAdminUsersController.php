<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\Core\Service\UserService;
use Zend\View\Model\JsonModel;

/**
 * Api Admin Users Controller
 *
 * Class ApiAdminUsersController
 * @package Monarc\FrontOffice\Controller
 */
class ApiAdminUsersController extends \Monarc\Core\Controller\AbstractController
{
    protected $name = 'users';

    /**
     * @inheritdoc
     */
    public function getList()
    {
        $page = $this->params()->fromQuery('page');
        $limit = $this->params()->fromQuery('limit');
        $order = $this->params()->fromQuery('order');
        $filter = $this->params()->fromQuery('filter');
        $status = $this->params()->fromQuery('status');
        if (is_null($status)) {
            $status = 1;
        }
        $filterAnd = ($status == "all") ? null : ['status' => (int) $status] ;

        $service = $this->getService();

        $entities = $service->getList($page, $limit, $order, $filter, $filterAnd);
        if (count($this->dependencies)) {
            foreach ($entities as $key => $entity) {
                $this->formatDependencies($entities[$key], $this->dependencies);
            }
        }

        return new JsonModel(array(
            'count' => $service->getFilteredCount($filter, $filterAnd),
            $this->name => $entities
        ));
    }

    /**
     * @inheritdoc
     */
    public function create($data)
    {
        /** @var UserService $service */
        $service = $this->getService();

        // Security: Don't allow changing role, password, status and history fields. To clean later.
        if (isset($data['salt'])) unset($data['salt']);
        if (isset($data['dateStart'])) unset($data['dateStart']);
        if (isset($data['dateEnd'])) unset($data['dateEnd']);

        $service->create($data);

        return new JsonModel(['status' => 'ok']);
    }

    /**
     * @inheritdoc
     */
    public function update($id, $data)
    {
        /** @var UserService $service */
        $service = $this->getService();

        // Security: Don't allow changing role, password, status and history fields. To clean later.
        if (isset($data['status'])) unset($data['status']);
        if (isset($data['id'])) unset($data['id']);
        if (isset($data['salt'])) unset($data['salt']);
        if (isset($data['updatedAt'])) unset($data['updatedAt']);
        if (isset($data['updater'])) unset($data['updater']);
        if (isset($data['createdAt'])) unset($data['createdAt']);
        if (isset($data['creator'])) unset($data['creator']);
        if (isset($data['dateStart'])) unset($data['dateStart']);
        if (isset($data['dateEnd'])) unset($data['dateEnd']);

        $service->update($id, $data);

        return new JsonModel(['status' => 'ok']);
    }

    /**
     * @inheritdoc
     */
    public function get($id)
    {
        /** @var UserService $service */
        $service = $this->getService();
        $entity = $service->getCompleteUser($id);

        if (count($this->dependencies)) {
            $this->formatDependencies($entity, $this->dependencies);
        }

        return new JsonModel($entity);
    }
}

