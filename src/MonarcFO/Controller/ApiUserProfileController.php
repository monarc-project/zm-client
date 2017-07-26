<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
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
     * @inheritdoc
     */
    public function getList()
    {
        $user = $this->connectedUser->getConnectedUser();
        unset($user['password']);
        return new JsonModel($user);
    }

    /**
     * @inheritdoc
     */
    public function patchList($data)
    {
        return new JsonModel($this->getService()->update($this->connectedUser->getConnectedUser(), $data));
    }

    /**
     * @inheritdoc
     */
    public function replaceList($data)
    {
        return new JsonModel($this->getService()->update($this->connectedUser->getConnectedUser(), $data));
    }

    /**
     * @inheritdoc
     */
    public function patch($id, $data)
    {
        return $this->methodNotAllowed();
    }

    /**
     * @inheritdoc
     */
    public function update($id, $data)
    {
        return $this->methodNotAllowed();
    }

    /**
     * @inheritdoc
     */
    public function get($id)
    {
        return $this->methodNotAllowed();
    }

    /**
     * @inheritdoc
     */
    public function delete($id)
    {
        return $this->methodNotAllowed();
    }

    /**
     * @inheritdoc
     */
    public function create($data)
    {
        return $this->methodNotAllowed();
    }
}