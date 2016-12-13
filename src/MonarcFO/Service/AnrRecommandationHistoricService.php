<?php
namespace MonarcFO\Service;

use MonarcFO\Model\Entity\InstanceRisk;
use MonarcFO\Model\Entity\InstanceRiskOp;
use MonarcFO\Model\Entity\Object;
use MonarcFO\Model\Table\InstanceRiskOpTable;
use MonarcFO\Model\Table\InstanceRiskTable;
use MonarcFO\Model\Table\RecommandationMeasureTable;
use MonarcFO\Model\Table\RecommandationRiskTable;
use MonarcFO\Model\Table\RecommandationTable;
use MonarcFO\Service\AbstractService;

/**
 * Anr Recommandation Historic Service
 *
 * Class AnrRecommandationHistoricService
 * @package MonarcFO\Service
 */
class AnrRecommandationHistoricService extends \MonarcCore\Service\AbstractService
{
    protected $dependencies = ['anr'];

}