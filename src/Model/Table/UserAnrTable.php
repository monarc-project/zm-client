<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Model\Table;

use Monarc\Core\Model\Entity\AnrSuperClass;
use Monarc\Core\Model\Entity\UserSuperClass;
use Monarc\Core\Model\Table\AbstractEntityTable;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Model\DbCli;
use Monarc\FrontOffice\Model\Entity\UserAnr;

/**
 * Class UserAnrTable
 * @package Monarc\FrontOffice\Model\Table
 */
class UserAnrTable extends AbstractEntityTable
{
    public function __construct(DbCli $dbCli, ConnectedUserService $connectedUserService)
    {
        parent::__construct($dbCli, UserAnr::class, $connectedUserService);
    }

    public function findByAnrAndUser(AnrSuperClass $anr, UserSuperClass $user): ?UserAnr
    {
        return $this->getRepository()
            ->createQueryBuilder('ua')
            ->where('ua.anr = :anr')
            ->andWhere('ua.user = :user')
            ->setParameter('anr', $anr)
            ->setParameter('user', $user)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
