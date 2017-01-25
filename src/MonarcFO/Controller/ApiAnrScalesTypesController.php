<?php
namespace MonarcFO\Controller;

use Zend\View\Model\JsonModel;

/**
 * Api ANR Scales Type Controller
 *
 * Class ApiAnrScalesTypesController
 * @package MonarcFO\Controller
 */
class ApiAnrScalesTypesController extends ApiAnrAbstractController
{
    protected $name = 'types';
    protected $dependencies = ['scale'];
}