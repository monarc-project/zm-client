<?php
namespace MonarcFO\Model\Table;

use MonarcCore\Model\Table\AbstractEntityTable;

/**
 * Class RecommandationTable
 * @package MonarcFO\Model\Table
 */
class RecommandationTable extends AbstractEntityTable
{
    /**
     * RecommandationTable constructor.
     * @param \MonarcCore\Model\Db $dbService
     */
    public function __construct(\MonarcCore\Model\Db $dbService)
    {
        parent::__construct($dbService, '\MonarcFO\Model\Entity\Recommandation');
    }
}