<?php
namespace MonarcFO\Service\Model\Table;

use MonarcCore\Service\Model\Table\AbstractServiceModelTable;
use Zend\ServiceManager\ServiceLocatorInterface;

class UserServiceModelTable extends AbstractServiceModelTable
{
    protected $dbService = '\MonarcCli\Model\Db';
    public function createService(ServiceLocatorInterface $serviceLocator){
        $instance = parent::createService($serviceLocator);

        if($instance !== false){
            $instance->setUserRoleTable($serviceLocator->get('\MonarcFO\Model\Table\UserRoleTable'));
            $instance->setUserTokenTable($serviceLocator->get('\MonarcFO\Model\Table\UserTokenTable'));
            $instance->setPasswordTokenTable($serviceLocator->get('\MonarcFO\Model\Table\PasswordTokenTable'));
        }
        return $instance;
    }
}
