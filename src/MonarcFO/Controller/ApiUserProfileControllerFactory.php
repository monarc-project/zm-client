<?php

namespace MonarcFO\Controller;

class ApiUserProfileControllerFactory extends AbstractControllerFactory
{
    protected $serviceName = array(
    	'service' => '\MonarcCore\Service\UserProfileService',
    	'connectedUser' => '\MonarcCore\Service\ConnectedUserService',
    );
}
