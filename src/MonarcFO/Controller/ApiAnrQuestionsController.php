<?php

namespace MonarcFO\Controller;

use MonarcCore\Model\Entity\AbstractEntity;
use Zend\View\Model\JsonModel;

/**
 * Api Anr Questions Controller
 *
 * Class ApiAnrQuestionsController
 * @package MonarcFO\Controller
 */
class ApiAnrQuestionsController extends ApiAnrAbstractController
{
    protected $name = 'questions';

    protected $dependencies = ['anr'];

}
