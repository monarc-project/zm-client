<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2021 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Table;

use Doctrine\ORM\EntityManager;
use Monarc\Core\Table\UserTable as CoreUserTable;
use Monarc\FrontOffice\Entity\User;

class UserTable extends CoreUserTable
{
    public function __construct(EntityManager $entityManager, string $entityName = User::class)
    {
        parent::__construct($entityManager, $entityName);
    }
}