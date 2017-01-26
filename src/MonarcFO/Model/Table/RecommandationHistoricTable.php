<?php
namespace MonarcFO\Model\Table;

use MonarcCore\Model\Table\AbstractEntityTable;

/**
 * Class RecommandationHistoricTable
 * @package MonarcFO\Model\Table
 */
class RecommandationHistoricTable extends AbstractEntityTable
{
    /**
     * RecommandationHistoricTable constructor.
     * @param \MonarcCore\Model\Db $dbService
     */
    public function __construct(\MonarcCore\Model\Db $dbService)
    {
        parent::__construct($dbService, '\MonarcFO\Model\Entity\RecommandationHistoric');
    }
}