<?php
namespace MonarcFO\Model\Table;

/**
 * Class UserRoleTable
 * @package MonarcFO\Model\Table
 */
class UserRoleTable extends \MonarcCore\Model\Table\UserRoleTable
{
    /**
     * UserRoleTable constructor.
     * @param \MonarcCore\Model\Db $dbService
     */
    public function __construct(\MonarcCore\Model\Db $dbService)
    {
        parent::__construct($dbService, '\MonarcFO\Model\Entity\UserRole');
    }
}