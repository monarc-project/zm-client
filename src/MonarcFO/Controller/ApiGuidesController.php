<?php
namespace MonarcFO\Controller;

use MonarcCore\Controller\AbstractController;
use Zend\View\Model\JsonModel;

/**
 * Api Guides Controller
 *
 * Class ApiGuidesController
 * @package MonarcFO\Controller
 */
class ApiGuidesController extends AbstractController
{
    protected $name = 'guides';

    protected $dependencies = ['anr'];
}