<?php
namespace MonarcFO\Controller;

use MonarcCore\Controller\AbstractControllerFactory;

/**
 * Api Anr Treatment Plan Controller Factory
 *
 * Class ApiAnrTreatmentPlanControllerFactory
 * @package MonarcFO\Controller
 */
class ApiAnrTreatmentPlanControllerFactory extends AbstractControllerFactory
{
    protected $serviceName = '\MonarcFO\Service\AnrRecommandationRiskService';
}