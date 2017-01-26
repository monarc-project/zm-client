<?php
namespace MonarcFO\Model\Table;

use MonarcCore\Model\Table\AbstractEntityTable;

/**
 * Class AmvTable
 * @package MonarcFO\Model\Table
 */
class AmvTable extends AbstractEntityTable
{
    /**
     * AmvTable constructor.
     * @param \MonarcCore\Model\Db $dbService
     */
    public function __construct(\MonarcCore\Model\Db $dbService)
    {
        parent::__construct($dbService, '\MonarcFO\Model\Entity\Amv');
    }
}