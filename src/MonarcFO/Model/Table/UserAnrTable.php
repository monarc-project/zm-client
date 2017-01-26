<?php
namespace MonarcFO\Model\Table;

use MonarcCore\Model\Table\AbstractEntityTable;

/**
 * Class UserAnrTable
 * @package MonarcFO\Model\Table
 */
class UserAnrTable extends AbstractEntityTable
{
    /**
     * UserAnrTable constructor.
     * @param \MonarcCore\Model\Db $dbService
     */
    public function __construct(\MonarcCore\Model\Db $dbService)
    {
        parent::__construct($dbService, '\MonarcFO\Model\Entity\UserAnr');
    }
}