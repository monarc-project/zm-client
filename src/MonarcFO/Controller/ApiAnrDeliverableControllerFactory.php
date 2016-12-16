<?php

namespace MonarcFO\Controller;

use MonarcCore\Controller\AbstractControllerFactory;

class ApiAnrDeliverableControllerFactory extends \MonarcCore\Controller\AbstractControllerFactory
{
    protected $serviceName = '\MonarcFO\Service\DeliverableGenerationService';
}

