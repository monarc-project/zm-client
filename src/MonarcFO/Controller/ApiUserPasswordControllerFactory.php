<?php
namespace MonarcFO\Controller;

use MonarcCore\Controller\AbstractControllerFactory;

/**
 * Api User Password Controler Factory
 *
 * Class ApiUserPasswordControllerFactory
 * @package MonarcFO\Controller
 */
class ApiUserPasswordControllerFactory extends AbstractControllerFactory
{
    protected $serviceName = '\MonarcFO\Service\PasswordService';
}