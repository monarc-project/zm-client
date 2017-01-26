<?php
namespace MonarcFO\Model\Table;

use MonarcCore\Model\Table\AbstractEntityTable;

/**
 * Class InterviewTable
 * @package MonarcFO\Model\Table
 */
class InterviewTable extends AbstractEntityTable
{
    /**
     * InterviewTable constructor.
     * @param \MonarcCore\Model\Db $dbService
     */
    public function __construct(\MonarcCore\Model\Db $dbService)
    {
        parent::__construct($dbService, '\MonarcFO\Model\Entity\Interview');
    }
}