<?php
namespace MonarcFO\Model\Table;


class ObjectTable extends \MonarcCore\Model\Table\ObjectTable {
    public function __construct(\MonarcCore\Model\Db $dbService) {
        parent::__construct($dbService, '\MonarcFO\Model\Entity\Object');
    }
}