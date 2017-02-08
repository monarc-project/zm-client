<?php
/**
 * @link      https://github.com/CASES-LU for the canonical source repository
 * @copyright Copyright (c) Cases is a registered trademark of SECURITYMADEIN.LU
 * @license   MyCases is licensed under the GNU Affero GPL v3 - See license.txt for more information
 */

namespace MonarcFO\Controller;

use MonarcCore\Service\UserService;
use Zend\View\Model\JsonModel;

/**
 * Api Admin Users Controller
 *
 * Class ApiAdminUsersController
 * @package MonarcFO\Controller
 */
class ApiAdminUsersController extends \MonarcCore\Controller\AbstractController
{
    protected $name = 'users';

    /**
     * Get list
     *
     * @return JsonModel
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
            'count' => $service->getFilteredCount($page, $limit, $order, $filter, $filterAnd),
            $this->name => $entities
        ));
    }

    /**
     * Create
     *
     * @param mixed $data
     * @return JsonModel
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
     * Update
     *
     * @param mixed $id
     * @param mixed $data
     * @return JsonModel
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
     * Get
     *
     * @param mixed $id
     * @return JsonModel
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

