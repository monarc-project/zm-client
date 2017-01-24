<?php
namespace MonarcFO\Model\Table;

class InstanceTable extends \MonarcCore\Model\Table\InstanceTable
{
    public function __construct(\MonarcCore\Model\Db $dbService)
    {
        parent::__construct($dbService, '\MonarcFO\Model\Entity\Instance');
    }
}