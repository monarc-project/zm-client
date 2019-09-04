<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\Core\Controller\AbstractControllerFactory;

/**
 * Api User Profile Controller Factory
 *
 * Class ApiUserProfileControllerFactory
 * @package Monarc\FrontOffice\Controller
 */
class ApiUserProfileControllerFactory extends AbstractControllerFactory
{
    protected $serviceName = [
        'service' => '\Monarc\Core\Service\UserProfileService',
        'connectedUser' => '\Monarc\Core\Service\ConnectedUserService',
    ];
}
