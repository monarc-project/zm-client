<?php
namespace MonarcFO\Controller;

use MonarcCore\Controller\AbstractControllerFactory;

/**
 * Api Config Controlelr Factory
 *
 * Class ApiConfigControllerFactory
 * @package MonarcFO\Controller
 */
class ApiConfigControllerFactory extends AbstractControllerFactory
{
    protected $serviceName = 'MonarcCore\Service\ConfigService';
}