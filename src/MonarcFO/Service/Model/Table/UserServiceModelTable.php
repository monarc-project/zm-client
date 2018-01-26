<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2018 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
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
