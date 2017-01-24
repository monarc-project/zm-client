<?php
namespace MonarcFO\Model\Table;

class ObjectObjectTable extends \MonarcCore\Model\Table\ObjectObjectTable
{
    public function __construct(\MonarcCore\Model\Db $dbService)
    {
        parent::__construct($dbService, '\MonarcFO\Model\Entity\ObjectObject');
    }
}