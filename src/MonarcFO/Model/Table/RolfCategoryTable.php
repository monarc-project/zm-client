<?php
namespace MonarcFO\Model\Table;

use MonarcCore\Model\Table\AbstractEntityTable;

/**
 * Class RolfCategoryTable
 * @package MonarcFO\Model\Table
 */
class RolfCategoryTable extends AbstractEntityTable
{
    /**
     * RolfCategoryTable constructor.
     * @param \MonarcCore\Model\Db $dbService
     */
    public function __construct(\MonarcCore\Model\Db $dbService)
    {
        parent::__construct($dbService, '\MonarcFO\Model\Entity\RolfCategory');
    }
}