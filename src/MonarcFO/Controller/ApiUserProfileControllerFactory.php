<?php
namespace MonarcFO\Controller;

use MonarcCore\Controller\AbstractControllerFactory;

/**
 * Api User Profile Controller Factory
 *
 * Class ApiUserProfileControllerFactory
 * @package MonarcFO\Controller
 */
class ApiUserProfileControllerFactory extends AbstractControllerFactory
{
    protected $serviceName = [
        'service' => '\MonarcCore\Service\UserProfileService',
        'connectedUser' => '\MonarcCore\Service\ConnectedUserService',
    ];
}