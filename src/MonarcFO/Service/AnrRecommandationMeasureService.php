<?php
namespace MonarcFO\Service;

use MonarcFO\Service\AbstractService;

/**
 * Anr Recommandation Measure Service
 *
 * Class AnrRecommandationMeasureService
 * @package MonarcFO\Service
 */
class AnrRecommandationMeasureService extends \MonarcCore\Service\AbstractService
{
    protected $dependencies = ['anr', 'recommandation', 'measure'];
    protected $anrTable;
    protected $recommandationTable;
    protected $measureTable;

}