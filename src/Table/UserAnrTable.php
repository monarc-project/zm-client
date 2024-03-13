<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Table;

use Doctrine\ORM\EntityManager;
use Monarc\Core\Model\Entity\AnrSuperClass;
use Monarc\Core\Model\Entity\UserSuperClass;
use Monarc\Core\Table\AbstractTable;
use Monarc\FrontOffice\Model\Entity\UserAnr;

class UserAnrTable extends AbstractTable
{
    public function __construct(EntityManager $entityManager, string $entityName = UserAnr::class)
    {
        parent::__construct($entityManager, $entityName);
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
