<?php
namespace MonarcFO\Model\Table;

use MonarcCore\Model\Table\AbstractEntityTable;

/**
 * Class RolfRiskTable
 * @package MonarcFO\Model\Table
 */
class RolfRiskTable extends AbstractEntityTable
{
    /**
     * RolfRiskTable constructor.
     * @param \MonarcCore\Model\Db $dbService
     */
    public function __construct(\MonarcCore\Model\Db $dbService)
    {
        parent::__construct($dbService, '\MonarcFO\Model\Entity\RolfRisk');
    }
}