<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Model\Table;

use Monarc\Core\Model\Table\PasswordTokenTable;
use Monarc\Core\Model\Table\UserTable as CoreUserTable;
use Monarc\Core\Model\Table\UserTokenTable;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Model\DbCli;
use Monarc\FrontOffice\Model\Entity\User;

/**
 * TODO: this class will be removed when we get rid of the AbstractEntityTable inheritance.
 * this class is used only to redefine entity class and used for abstract generic methods,
 * but our goal to avoid the generic code style and make it simple.
 *
 * Class UserTable
 * @package Monarc\FrontOffice\Model\Table
 */
class UserTable extends CoreUserTable
{
    public function __construct(
        DbCli $dbService,
        ConnectedUserService $connectedUserService,
        UserTokenTable $userTokenTable,
        PasswordTokenTable $passwordTokenTable
    ) {
        parent::__construct(
            $dbService,
            $connectedUserService,
            $userTokenTable,
            $passwordTokenTable
        );
    }

    public function getEntityClass(): string
    {
        return User::class;
    }
}
