<?php
namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;

class UserAnrServiceFactory extends AbstractServiceFactory
{
    protected $ressources = array(
        'table'=> '\MonarcFO\Model\Table\UserAnrTable',
        'entity'=> '\MonarcFO\Model\Entity\UserAnr',
        'anrTable'=> '\MonarcFO\Model\Table\AnrTable',
        'userTable'=> '\MonarcFO\Model\Table\UserTable',
    );
}
