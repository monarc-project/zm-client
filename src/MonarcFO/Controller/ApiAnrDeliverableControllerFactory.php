<?php
namespace MonarcFO\Controller;

use MonarcCore\Controller\AbstractControllerFactory;

/**
 * Api Anr Deliverable Controller Factory
 *
 * Class ApiAnrDeliverableControllerFactory
 * @package MonarcFO\Controller
 */
class ApiAnrDeliverableControllerFactory extends AbstractControllerFactory
{
    protected $serviceName = '\MonarcFO\Service\DeliverableGenerationService';
}