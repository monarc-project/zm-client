<?php
namespace MonarcFO\Model\Table;

use MonarcCore\Model\Table\AbstractEntityTable;

/**
 * Class AnrObjectCategoryTable
 * @package MonarcFO\Model\Table
 */
class AnrObjectCategoryTable extends AbstractEntityTable
{
    /**
     * AnrObjectCategoryTable constructor.
     * @param \MonarcCore\Model\Db $dbService
     */
    public function __construct(\MonarcCore\Model\Db $dbService)
    {
        parent::__construct($dbService, '\MonarcFO\Model\Entity\AnrObjectCategory');
    }
}