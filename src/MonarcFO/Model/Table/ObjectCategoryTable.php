<?php
namespace MonarcFO\Model\Table;

use MonarcCore\Model\Table\AbstractEntityTable;

/**
 * Class ObjectCategoryTable
 * @package MonarcFO\Model\Table
 */
class ObjectCategoryTable extends AbstractEntityTable
{
    /**
     * ObjectCategoryTable constructor.
     * @param \MonarcCore\Model\Db $dbService
     */
    public function __construct(\MonarcCore\Model\Db $dbService)
    {
        parent::__construct($dbService, '\MonarcFO\Model\Entity\ObjectCategory');
    }
}