<?php
namespace MonarcFO\Controller;

use MonarcCore\Controller\AbstractControllerFactory;

/**
 * Api Anr Vulnerabilities Controller Factory
 *
 * Class ApiAnrVulnerabilitiesControllerFactory
 * @package MonarcFO\Controller
 */
class ApiAnrVulnerabilitiesControllerFactory extends AbstractControllerFactory
{
    protected $serviceName = '\MonarcFO\Service\AnrVulnerabilityService';
}