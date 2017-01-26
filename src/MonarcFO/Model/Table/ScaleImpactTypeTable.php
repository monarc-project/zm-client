<?php
namespace MonarcFO\Model\Table;

use MonarcCore\Model\Table\AbstractEntityTable;

/**
 * Class ScaleImpactTypeTable
 * @package MonarcFO\Model\Table
 */
class ScaleImpactTypeTable extends AbstractEntityTable
{
    /**
     * ScaleImpactTypeTable constructor.
     * @param \MonarcCore\Model\Db $dbService
     */
    public function __construct(\MonarcCore\Model\Db $dbService)
    {
        parent::__construct($dbService, '\MonarcFO\Model\Entity\ScaleImpactType');
    }
}