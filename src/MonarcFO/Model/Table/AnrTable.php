<?php
namespace MonarcFO\Model\Table;

use MonarcCore\Model\Table\AbstractEntityTable;

/**
 * Class AnrTable
 * @package MonarcFO\Model\Table
 */
class AnrTable extends AbstractEntityTable
{
    /**
     * AnrTable constructor.
     * @param \MonarcCore\Model\Db $dbService
     */
    public function __construct(\MonarcCore\Model\Db $dbService)
    {
        parent::__construct($dbService, '\MonarcFO\Model\Entity\Anr');
    }
}