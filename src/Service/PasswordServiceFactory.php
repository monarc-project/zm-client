<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Service\AbstractServiceFactory;

/**
 * TODO: should be possible to burn this class after constructor injection, it duplicates the Core one.
 * Proxy class to instantiate Monarc\Core's PasswordService, with Monarc\FrontOffice's services
 * @package Monarc\FrontOffice\Service
 */
class PasswordServiceFactory extends AbstractServiceFactory
{
    protected $class = "\\Monarc\Core\\Service\\PasswordService";

    protected $ressources = [
        'entity' => 'Monarc\FrontOffice\Model\Entity\PasswordToken',
        'table' => 'Monarc\FrontOffice\Model\Table\PasswordTokenTable',
        'userTable' => 'Monarc\FrontOffice\Model\Table\UserTable',
        'userService' => 'Monarc\FrontOffice\Service\UserService',
        'mailService' => 'Monarc\Core\Service\MailService',
        'configService' => 'Monarc\Core\Service\ConfigService'
    ];
}
