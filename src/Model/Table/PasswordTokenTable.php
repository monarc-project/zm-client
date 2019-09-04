<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Model\Table;

use Monarc\Core\Model\Db;
use Monarc\FrontOffice\Model\Entity\PasswordToken;

/**
 * Class PasswordTokenTable
 * @package Monarc\FrontOffice\Model\Table
 */
class PasswordTokenTable extends \Monarc\Core\Model\Table\PasswordTokenTable
{
    /**
     * PasswordTokenTable constructor.
     * @param Db $dbService
     */
    public function __construct(Db $dbService)
    {
        parent::__construct($dbService, PasswordToken::class);
    }
}
