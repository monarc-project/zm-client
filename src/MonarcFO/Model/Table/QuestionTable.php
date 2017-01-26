<?php
namespace MonarcFO\Model\Table;

use MonarcCore\Model\Table\AbstractEntityTable;

/**
 * Class QuestionTable
 * @package MonarcFO\Model\Table
 */
class QuestionTable extends AbstractEntityTable
{
    /**
     * QuestionTable constructor.
     * @param \MonarcCore\Model\Db $dbService
     */
    public function __construct(\MonarcCore\Model\Db $dbService)
    {
        parent::__construct($dbService, '\MonarcFO\Model\Entity\Question');
    }
}