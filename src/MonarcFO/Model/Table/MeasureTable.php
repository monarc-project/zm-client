<?php
namespace MonarcFO\Model\Table;

use MonarcCore\Model\Table\AbstractEntityTable;

/**
 * Class MeasureTable
 * @package MonarcFO\Model\Table
 */
class MeasureTable extends AbstractEntityTable
{
    /**
     * MeasureTable constructor.
     * @param \MonarcCore\Model\Db $dbService
     */
    public function __construct(\MonarcCore\Model\Db $dbService)
    {
        parent::__construct($dbService, '\MonarcFO\Model\Entity\Measure');
    }
}