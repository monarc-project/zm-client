<?php
namespace MonarcFO\Model\Table;

/**
 * Class ObjectTable
 * @package MonarcFO\Model\Table
 */
class ObjectTable extends \MonarcCore\Model\Table\ObjectTable
{
    /**
     * ObjectTable constructor.
     * @param \MonarcCore\Model\Db $dbService
     */
    public function __construct(\MonarcCore\Model\Db $dbService)
    {
        parent::__construct($dbService, '\MonarcFO\Model\Entity\Object');
    }
}