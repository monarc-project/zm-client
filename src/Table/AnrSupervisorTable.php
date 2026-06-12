<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2026 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Table;

use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Query\Expr;
use Monarc\Core\Table\AbstractTable;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Entity\AnrSupervisor;
use Monarc\FrontOffice\Entity\User;

class AnrSupervisorTable extends AbstractTable
{
    public function __construct(EntityManager $entityManager, string $entityName = AnrSupervisor::class)
    {
        parent::__construct($entityManager, $entityName);
    }

    /**
     * @return AnrSupervisor[]
     */
    public function findByAnrOrdered(Anr $anr): array
    {
        return $this->getRepository()->createQueryBuilder('s')
            ->where('s.anr = :anr')
            ->setParameter('anr', $anr)
            ->addOrderBy('s.isActive', Criteria::DESC)
            ->addOrderBy('s.name', Criteria::ASC)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return AnrSupervisor[]
     */
    public function findByAnrFiltered(
        Anr $anr,
        ?string $filter = null,
        ?string $role = null,
        ?bool $isActive = null
    ): array {
        $queryBuilder = $this->getRepository()->createQueryBuilder('s')
            ->where('s.anr = :anr')
            ->setParameter('anr', $anr)
            ->addOrderBy('s.isActive', Criteria::DESC)
            ->addOrderBy('s.name', Criteria::ASC);

        if ($role !== null && $role !== '') {
            $queryBuilder->innerJoin('s.roles', 'sr', Expr\Join::WITH, 'sr.role = :role')
                ->setParameter('role', $role);
        }

        if ($isActive !== null) {
            $queryBuilder->andWhere('s.isActive = :isActive')
                ->setParameter('isActive', $isActive);
        }

        $normalizedFilter = trim((string)$filter);
        if ($normalizedFilter !== '') {
            $queryBuilder->andWhere(
                'LOWER(s.name) LIKE :filter OR LOWER(COALESCE(s.email, \'\')) LIKE :filter'
            )->setParameter('filter', '%' . mb_strtolower($normalizedFilter) . '%');
        }

        return $queryBuilder->getQuery()->getResult();
    }

    public function findLinkedActiveByAnrAndUser(Anr $anr, User $user): ?AnrSupervisor
    {
        return $this->getRepository()->createQueryBuilder('s')
            ->where('s.anr = :anr')
            ->andWhere('s.linkedUser = :user')
            ->andWhere('s.isActive = 1')
            ->setParameter('anr', $anr)
            ->setParameter('user', $user)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function hasLinkedActiveRole(Anr $anr, User $user, string $role): bool
    {
        return (bool)$this->getRepository()->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->innerJoin('s.roles', 'sr', Expr\Join::WITH, 'sr.role = :role')
            ->where('s.anr = :anr')
            ->andWhere('s.linkedUser = :user')
            ->andWhere('s.isActive = 1')
            ->setParameter('anr', $anr)
            ->setParameter('user', $user)
            ->setParameter('role', $role)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return AnrSupervisor[]
     */
    public function findLinkedActiveByUser(User $user): array
    {
        return $this->getRepository()->createQueryBuilder('s')
            ->where('s.linkedUser = :user')
            ->andWhere('s.isActive = 1')
            ->setParameter('user', $user)
            ->orderBy('s.name', Criteria::ASC)
            ->getQuery()
            ->getResult();
    }

    public function findOneByAnrAndNormalizedIdentity(
        Anr $anr,
        ?string $email,
        ?string $name,
        ?int $excludeSupervisorId = null
    ): ?AnrSupervisor {
        $queryBuilder = $this->getRepository()->createQueryBuilder('s')
            ->where('s.anr = :anr')
            ->setParameter('anr', $anr)
            ->setMaxResults(1);

        if ($excludeSupervisorId !== null) {
            $queryBuilder->andWhere('s.id != :excludeSupervisorId')
                ->setParameter('excludeSupervisorId', $excludeSupervisorId);
        }

        $normalizedEmail = trim((string)$email);
        if ($normalizedEmail !== '') {
            $queryBuilder->andWhere('LOWER(s.email) = :email')
                ->setParameter('email', mb_strtolower($normalizedEmail));

            return $queryBuilder->getQuery()->getOneOrNullResult();
        }

        $normalizedName = trim((string)$name);
        if ($normalizedName === '') {
            return null;
        }

        $queryBuilder = $this->getRepository()->createQueryBuilder('s')
            ->where('s.anr = :anr')
            ->andWhere('LOWER(s.name) = :name')
            ->setParameter('anr', $anr)
            ->setParameter('name', mb_strtolower($normalizedName))
            ->setMaxResults(1);
        if ($excludeSupervisorId !== null) {
            $queryBuilder->andWhere('s.id != :excludeSupervisorId')
                ->setParameter('excludeSupervisorId', $excludeSupervisorId);
        }

        return $queryBuilder->getQuery()->getOneOrNullResult();
    }
}
