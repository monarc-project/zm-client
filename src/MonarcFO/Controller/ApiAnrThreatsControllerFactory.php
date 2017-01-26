<?php
namespace MonarcFO\Controller;

use MonarcCore\Controller\AbstractControllerFactory;

/**
 * Api Anr Threats Controller Factory
 *
 * Class ApiAnrThreatsControllerFactory
 * @package MonarcFO\Controller
 */
class ApiAnrThreatsControllerFactory extends AbstractControllerFactory
{
    protected $serviceName = '\MonarcFO\Service\AnrThreatService';
}