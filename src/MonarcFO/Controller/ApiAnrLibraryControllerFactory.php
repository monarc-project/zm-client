<?php
namespace MonarcFO\Controller;

use MonarcCore\Controller\AbstractControllerFactory;

/**
 * Api Anr Library Controller Factory
 *
 * Class ApiAnrLibraryControllerFactory
 * @package MonarcFO\Controller
 */
class ApiAnrLibraryControllerFactory extends AbstractControllerFactory
{
    protected $serviceName = '\MonarcFO\Service\AnrObjectService';
}