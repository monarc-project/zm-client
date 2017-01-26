<?php
namespace MonarcFO\Controller;

use MonarcCore\Controller\AbstractControllerFactory;

/**
 * Api Client Controller Factory
 *
 * Class ApiClientsControllerFactory
 * @package MonarcFO\Controller
 */
class ApiClientsControllerFactory extends AbstractControllerFactory
{
    protected $serviceName = '\MonarcFO\Service\ClientService';
}