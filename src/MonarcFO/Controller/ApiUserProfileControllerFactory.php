<?php

namespace MonarcFO\Controller;

class ApiUserProfileControllerFactory extends \MonarcCore\Controller\AbstractControllerFactory
{
    protected $serviceName = array(
        'service' => '\MonarcCore\Service\UserProfileService',
        'connectedUser' => '\MonarcCore\Service\ConnectedUserService',
    );
}
