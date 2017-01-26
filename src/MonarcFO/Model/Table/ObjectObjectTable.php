<?php
namespace MonarcFO\Model\Table;

/**
 * Class ObjectObjectTable
 * @package MonarcFO\Model\Table
 */
class ObjectObjectTable extends \MonarcCore\Model\Table\ObjectObjectTable
{
    /**
     * ObjectObjectTable constructor.
     * @param \MonarcCore\Model\Db $dbService
     */
    public function __construct(\MonarcCore\Model\Db $dbService)
    {
        parent::__construct($dbService, '\MonarcFO\Model\Entity\ObjectObject');
    }
}