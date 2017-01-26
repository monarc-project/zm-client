<?php
namespace MonarcFO\Model\Table;

use MonarcCore\Model\Table\AbstractEntityTable;

/**
 * Class ScaleTable
 * @package MonarcFO\Model\Table
 */
class ScaleTable extends AbstractEntityTable
{
    /**
     * ScaleTable constructor.
     * @param \MonarcCore\Model\Db $dbService
     */
    public function __construct(\MonarcCore\Model\Db $dbService)
    {
        parent::__construct($dbService, '\MonarcFO\Model\Entity\Scale');
    }
}