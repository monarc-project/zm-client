<?php
/**
 * @link      https://github.com/CASES-LU for the canonical source repository
 * @copyright Copyright (c) Cases is a registered trademark of SECURITYMADEIN.LU
 * @license   MyCases is licensed under the GNU Affero GPL v3 - See license.txt for more information
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

