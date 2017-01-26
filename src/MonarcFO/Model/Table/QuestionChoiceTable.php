<?php
namespace MonarcFO\Model\Table;

use MonarcCore\Model\Table\AbstractEntityTable;

/**
 * Class QuestionChoiceTable
 * @package MonarcFO\Model\Table
 */
class QuestionChoiceTable extends AbstractEntityTable
{
    /**
     * QuestionChoiceTable constructor.
     * @param \MonarcCore\Model\Db $dbService
     */
    public function __construct(\MonarcCore\Model\Db $dbService)
    {
        parent::__construct($dbService, '\MonarcFO\Model\Entity\QuestionChoice');
    }
}