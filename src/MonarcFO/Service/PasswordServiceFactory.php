<?php
/**
 * @link      https://github.com/CASES-LU for the canonical source repository
 * @copyright Copyright (c) Cases is a registered trademark of SECURITYMADEIN.LU
 * @license   MyCases is licensed under the GNU Affero GPL v3 - See license.txt for more information
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