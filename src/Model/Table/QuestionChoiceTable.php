<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Model\Table;

use Monarc\Core\Model\Table\AbstractEntityTable;

/**
 * Class QuestionChoiceTable
 * @package Monarc\FrontOffice\Model\Table
 */
class QuestionChoiceTable extends AbstractEntityTable
{
    /**
     * QuestionChoiceTable constructor.
     * @param \Monarc\Core\Model\Db $dbService
     */
    public function __construct(\Monarc\Core\Model\Db $dbService)
    {
        parent::__construct($dbService, '\Monarc\FrontOffice\Model\Entity\QuestionChoice');
    }
}
