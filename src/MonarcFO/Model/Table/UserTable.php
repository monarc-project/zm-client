<?php
namespace MonarcFO\Model\Table;

/**
 * Class UserTable
 * @package MonarcFO\Model\Table
 */
class UserTable extends \MonarcCore\Model\Table\UserTable
{
    /**
     * UserTable constructor.
     * @param \MonarcCore\Model\Db $dbService
     */
    public function __construct(\MonarcCore\Model\Db $dbService)
    {
        parent::__construct($dbService, '\MonarcFO\Model\Entity\User');
    }
}