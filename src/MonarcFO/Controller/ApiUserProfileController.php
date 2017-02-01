<?php
/**
 * @link      https://github.com/CASES-LU for the canonical source repository
 * @copyright Copyright (c) Cases is a registered trademark of SECURITYMADEIN.LU
 * @license   MyCases is licensed under the GNU Affero GPL v3 - See license.txt for more information
 */

namespace MonarcFO\Controller;

use MonarcCore\Controller\AbstractController;
use Zend\View\Model\JsonModel;

/**
 * Api User Profile Controller
 *
 * Class ApiUserProfileController
 * @package MonarcFO\Controller
 */
class ApiUserProfileController extends AbstractController
{
    protected $connectedUser;

    /**
     * ApiUserProfileController constructor.
     * @param \MonarcCore\Service\AbstractServiceFactory $services
     */
    public function __construct($services)
    {
        if (!empty($services['service'])) {
            $this->service = $services['service'];
        }
        if (!empty($services['connectedUser'])) {
            $this->connectedUser = $services['connectedUser'];
        }
    }

    /**
     * Get List
     *
     * @return JsonModel
     */
    public function getList()
    {
        $user = $this->connectedUser->getConnectedUser();
        unset($user['password']);
        return new JsonModel($user);
    }

    /**
     * Patch List
     *
     * @param mixed $data
     * @return JsonModel
     */
    public function patchList($data)
    {
        return new JsonModel($this->getService()->update($this->connectedUser->getConnectedUser(), $data));
    }

    /**
     * Replace List
     *
     * @param mixed $data
     * @return JsonModel
     */
    public function replaceList($data)
    {
        return new JsonModel($this->getService()->update($this->connectedUser->getConnectedUser(), $data));
    }

    public function patch($id, $data)
    {
        return $this->methodNotAllowed();
    }

    public function update($id, $data)
    {
        return $this->methodNotAllowed();
    }

    public function get($id)
    {
        return $this->methodNotAllowed();
    }

    public function delete($id)
    {
        return $this->methodNotAllowed();
    }

    public function create($data)
    {
        return $this->methodNotAllowed();
    }
}