<?php
namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;
use Zend\ServiceManager\ServiceLocatorInterface;

/**
 * Password Service Factory
 *
 * Class PasswordServiceFactory
 * @package MonarcFO\Service
 */
class PasswordServiceFactory extends AbstractServiceFactory
{
    protected $class = "\\MonarcCore\\Service\\PasswordService";

    protected $ressources = [
        'entity' => 'MonarcFO\Model\Entity\PasswordToken',
        'table' => 'MonarcFO\Model\Table\PasswordTokenTable',
        'userTable' => 'MonarcFO\Model\Table\UserTable',
        'userService' => 'MonarcFO\Service\UserService',
        'mailService' => 'MonarcFO\Service\MailService'
    ];
}