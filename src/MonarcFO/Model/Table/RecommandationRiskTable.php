<?php
namespace MonarcFO\Model\Table;

use MonarcCore\Model\Table\AbstractEntityTable;

/**
 * Class RecommandationRiskTable
 * @package MonarcFO\Model\Table
 */
class RecommandationRiskTable extends AbstractEntityTable
{
    /**
     * RecommandationRiskTable constructor.
     * @param \MonarcCore\Model\Db $dbService
     */
    public function __construct(\MonarcCore\Model\Db $dbService)
    {
        parent::__construct($dbService, '\MonarcFO\Model\Entity\RecommandationRisk');
    }
}