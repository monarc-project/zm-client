<?php
namespace MonarcFO\Model\Table;

/**
 * Class UserTokenTable
 * @package MonarcFO\Model\Table
 */
class UserTokenTable extends \MonarcCore\Model\Table\UserTokenTable
{
    /**
     * UserTokenTable constructor.
     * @param \MonarcCore\Model\Db $dbService
     */
    public function __construct(\MonarcCore\Model\Db $dbService)
    {
        parent::__construct($dbService, '\MonarcFO\Model\Entity\UserToken');
    }
}