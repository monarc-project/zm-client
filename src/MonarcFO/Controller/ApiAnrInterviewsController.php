<?php

namespace MonarcFO\Controller;

use Zend\View\Model\JsonModel;

class ApiAnrInterviewsController extends ApiAnrAbstractController
{
    protected $name = 'interviews';

    protected $dependencies = ['anr'];
}

