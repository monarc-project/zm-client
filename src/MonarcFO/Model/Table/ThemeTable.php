<?php
namespace MonarcFO\Model\Table;

use MonarcCore\Model\Table\AbstractEntityTable;

/**
 * Class ThemeTable
 * @package MonarcFO\Model\Table
 */
class ThemeTable extends AbstractEntityTable
{
    /**
     * ThemeTable constructor.
     * @param \MonarcCore\Model\Db $dbService
     */
    public function __construct(\MonarcCore\Model\Db $dbService)
    {
        parent::__construct($dbService, '\MonarcFO\Model\Entity\Theme');
    }
}