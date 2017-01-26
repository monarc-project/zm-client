<?php
namespace MonarcFO\Controller;

use Zend\View\Model\JsonModel;

/**
 * Api Anr Interviews Controller
 *
 * Class ApiAnrInterviewsController
 * @package MonarcFO\Controller
 */
class ApiAnrInterviewsController extends ApiAnrAbstractController
{
    protected $name = 'interviews';

    protected $dependencies = ['anr'];
}