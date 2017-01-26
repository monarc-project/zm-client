<?php
namespace MonarcFO\Controller;

use MonarcCore\Controller\AbstractControllerFactory;

/**
 * Api Guides Controller Factory
 *
 * Class ApiGuidesControllerFactory
 * @package MonarcFO\Controller
 */
class ApiGuidesControllerFactory extends AbstractControllerFactory
{
    protected $serviceName = '\MonarcCore\Service\GuideService';
}