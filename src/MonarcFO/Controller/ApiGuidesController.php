<?php
namespace MonarcFO\Controller;

use MonarcCore\Controller\AbstractController;
use Zend\View\Model\JsonModel;

class ApiGuidesController extends AbstractController
{
    protected $name = 'guides';

    protected $dependencies = ['anr'];

}

