<?php
namespace MonarcFO\Model\Table;

use MonarcCore\Model\Table\AbstractEntityTable;

/**
 * Class RecommandationMeasureTable
 * @package MonarcFO\Model\Table
 */
class RecommandationMeasureTable extends AbstractEntityTable
{
    /**
     * RecommandationMeasureTable constructor.
     * @param \MonarcCore\Model\Db $dbService
     */
    public function __construct(\MonarcCore\Model\Db $dbService)
    {
        parent::__construct($dbService, '\MonarcFO\Model\Entity\RecommandationMeasure');
    }
}