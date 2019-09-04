<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service\Model\Table;

use Monarc\Core\Model\DbCli;
use Monarc\Core\Service\Model\Table\AbstractServiceModelTable;
use Zend\ServiceManager\ServiceLocatorInterface;

/**
 * Class UserServiceModelTable
 * @package Monarc\FrontOffice\Service\Model\Table
 */
class UserServiceModelTable extends AbstractServiceModelTable
{
    protected $dbService = DbCli::class;

    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        $instance = parent::createService($serviceLocator);

        if ($instance !== false) {
            $instance->setUserRoleTable($serviceLocator->get('\Monarc\FrontOffice\Model\Table\UserRoleTable'));
            $instance->setUserTokenTable($serviceLocator->get('\Monarc\FrontOffice\Model\Table\UserTokenTable'));
            $instance->setPasswordTokenTable($serviceLocator->get('\Monarc\FrontOffice\Model\Table\PasswordTokenTable'));
        }
        return $instance;
    }
}
