<?php

namespace MonarcFO\Controller;

use MonarcFO\Service\AnrRecommandationRiskService;
use Zend\View\Model\JsonModel;

/**
 * Api Anr Recommandations Historics
 *
 * Class ApiAnrRecommandationsHistoricsController
 * @package MonarcFO\Controller
 */
class ApiAnrRecommandationsHistoricsController extends ApiAnrAbstractController
{
    protected $name = 'recommandations-historics';
    protected $dependencies = ['anr'];
}