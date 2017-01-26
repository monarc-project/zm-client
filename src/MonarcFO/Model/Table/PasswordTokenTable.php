<?php
namespace MonarcFO\Model\Table;

/**
 * Class PasswordTokenTable
 * @package MonarcFO\Model\Table
 */
class PasswordTokenTable extends \MonarcCore\Model\Table\PasswordTokenTable
{
    /**
     * PasswordTokenTable constructor.
     * @param \MonarcCore\Model\Db $dbService
     */
    public function __construct(\MonarcCore\Model\Db $dbService)
    {
        parent::__construct($dbService, '\MonarcFO\Model\Entity\PasswordToken');
    }
}