<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service\Model\Table;

use Monarc\Core\Model\DbCli;
use Monarc\Core\Service\Model\Table\AbstractServiceModelTable;

/**
 * Class RecordServiceModelTable
 * @package Monarc\FrontOffice\Service\Model\Table
 */
class RecordServiceModelTable extends AbstractServiceModelTable
{
    protected $dbService = DbCli::class;
}
