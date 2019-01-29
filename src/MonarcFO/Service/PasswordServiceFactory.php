<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;

/**
 * Proxy class to instantiate MonarcCore's PasswordService, with MonarcFO's services
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
        'mailService' => 'MonarcFO\Service\MailService',
        'securityService' => 'MonarcCore\Service\SecurityService',
        'configService' => 'MonarcCore\Service\ConfigService'
    ];
}