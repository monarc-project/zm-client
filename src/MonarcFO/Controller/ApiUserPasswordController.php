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
 * Api User Password Controller
 *
 * Class ApiUserPasswordController
 * @package MonarcFO\Controller
 */
class ApiUserPasswordController extends AbstractController
{
    /**
     * @inheritdoc
     */
    public function update($id, $data)
    {
        if ($data['new'] == $data['confirm']) {
            $this->getService()->changePassword($id, $data['old'], $data['new']);
        } else {
            throw  new \MonarcCore\Exception\Exception('Password must be the same', 422);
        }

        return new JsonModel(['status' => 'ok']);
    }

    /**
     * @inheritdoc
     */
    public function patch($token, $data)
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

    /**
     * @inheritdoc
     */
    public function getList()
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
}