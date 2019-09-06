<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Model\Table;

use Monarc\Core\Model\DbCli;
use Monarc\Core\Model\Table\UserRoleTable as CoreUserRoleTable;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Model\Entity\UserRole;

/**
 * Class UserRoleTable
 * @package Monarc\FrontOffice\Model\Table
 */
class UserRoleTable extends CoreUserRoleTable
{
    public function __construct(DbCli $dbCli, ConnectedUserService $connectedUserService)
    {
        parent::__construct($dbCli, UserRole::class, $connectedUserService);
    }
}
