<?php
namespace MonarcFO\Model\Table;

abstract class AbstractEntityTable extends \MonarcCore\Model\Table\AbstractEntityTable
{
    public function __construct(\MonarcCore\Model\Db $dbService, $class = null)
    {
        $this->db = $dbService;
        if ($class != null) {
            $this->class = $class;
        } else {
            $thisClassName = get_class($this);
            $classParts = explode('\\', $thisClassName);
            $lastClassPart = end($classParts);
            $this->class = '\MonarcFO\Model\Entity\\' . substr($lastClassPart, 0, -5);
        }
    }
}
