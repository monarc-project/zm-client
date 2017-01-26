<?php
namespace MonarcFO\Model\Table;

use MonarcCore\Model\Table\AbstractEntityTable;

/**
 * Class ScaleCommentTable
 * @package MonarcFO\Model\Table
 */
class ScaleCommentTable extends AbstractEntityTable
{
    /**
     * ScaleCommentTable constructor.
     * @param \MonarcCore\Model\Db $dbService
     */
    public function __construct(\MonarcCore\Model\Db $dbService)
    {
        parent::__construct($dbService, '\MonarcFO\Model\Entity\ScaleComment');
    }
}