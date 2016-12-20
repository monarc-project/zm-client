<?php
namespace MonarcFO\Controller;

use MonarcCore\Controller\AbstractController;
use Zend\View\Model\JsonModel;

class ApiGuidesItemsController extends AbstractController
{
    protected $name = 'guides-items';

    protected $dependencies = ['anr', 'guide'];

}

