<?php
/**
 * @link      https://github.com/CASES-LU for the canonical source repository
 * @copyright Copyright (c) Cases is a registered trademark of SECURITYMADEIN.LU
 * @license   MyCases is licensed under the GNU Affero GPL v3 - See license.txt for more information
 */

namespace MonarcFO\Service\Model\Table;

use MonarcCore\Service\Model\Table\AbstractServiceModelTable;
use Zend\ServiceManager\ServiceLocatorInterface;

/**
 * Class UserServiceModelTable
 * @package MonarcFO\Service\Model\Table
 */
class UserServiceModelTable extends AbstractServiceModelTable
{
    protected $dbService = '\MonarcCli\Model\Db';

    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        $instance = parent::createService($serviceLocator);

        if ($instance !== false) {
            $instance->setUserRoleTable($serviceLocator->get('\MonarcFO\Model\Table\UserRoleTable'));
            $instance->setUserTokenTable($serviceLocator->get('\MonarcFO\Model\Table\UserTokenTable'));
            $instance->setPasswordTokenTable($serviceLocator->get('\MonarcFO\Model\Table\PasswordTokenTable'));
        }
        return $instance;
    }
}
