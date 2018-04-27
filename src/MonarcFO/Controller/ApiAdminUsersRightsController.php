<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2018 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Controller;

use MonarcFO\Service\UserAnrService;
use Zend\View\Model\JsonModel;

/**
 * Api Admin Users Rights Controller
 *
 * Class ApiAdminUsersRightsController
 * @package MonarcFO\Controller
 */
class ApiAdminUsersRightsController extends \MonarcCore\Controller\AbstractController
{
    protected $name = 'rights';

    /**
     * @inheritdoc
     */
    public function getList()
    {
        /** @var UserAnrService $service */
        $service = $this->getService();
        $rights = $service->getMatrix();

        return new JsonModel($rights);
    }

    /**
     * @inheritdoc
     */
    public function update($id, $data)
    {
        return $this->methodNotAllowed();
    }
}

