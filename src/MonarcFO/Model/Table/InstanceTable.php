<?php
namespace MonarcFO\Model\Table;

/**
 * Class InstanceTable
 * @package MonarcFO\Model\Table
 */
class InstanceTable extends \MonarcCore\Model\Table\InstanceTable
{
    /**
     * InstanceTable constructor.
     * @param \MonarcCore\Model\Db $dbService
     */
    public function __construct(\MonarcCore\Model\Db $dbService)
    {
        parent::__construct($dbService, '\MonarcFO\Model\Entity\Instance');
    }
}