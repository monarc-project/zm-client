<?php
namespace MonarcFO\Controller;

use Zend\View\Model\JsonModel;

/**
 * Api Anr Recommandations
 *
 * Class ApiAnrRecommandationsController
 * @package MonarcFO\Controller
 */
class ApiAnrRecommandationsController extends ApiAnrAbstractController
{
    protected $name = 'recommandations';
    protected $dependencies = ['anr'];
}