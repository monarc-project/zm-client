<?php
namespace MonarcFO\Model\Table;

use MonarcCore\Model\Table\AbstractEntityTable;

/**
 * Class RolfTagTable
 * @package MonarcFO\Model\Table
 */
class RolfTagTable extends AbstractEntityTable
{
    /**
     * RolfTagTable constructor.
     * @param \MonarcCore\Model\Db $dbService
     */
    public function __construct(\MonarcCore\Model\Db $dbService)
    {
        parent::__construct($dbService, '\MonarcFO\Model\Entity\RolfTag');
    }
}