<?php

namespace MonarcFO\Controller;

class ApiUserProfileControllerFactory extends \MonarcCore\Controller\AbstractControllerFactory
{
    protected $serviceName = [
        'service' => '\MonarcCore\Service\UserProfileService',
        'connectedUser' => '\MonarcCore\Service\ConnectedUserService',
    ];
}
