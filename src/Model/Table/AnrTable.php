<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Model\Table;

use Doctrine\ORM\EntityNotFoundException;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\QueryBuilder;
use Monarc\Core\Model\Entity\AnrSuperClass;
use Monarc\FrontOffice\Model\DbCli;
use Monarc\Core\Model\Table\AbstractEntityTable;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Model\Entity\Anr;
use Monarc\FrontOffice\Model\Entity\Snapshot;
use Monarc\FrontOffice\Model\Entity\UserAnr;

/**
 * Class AnrTable
 * @package Monarc\FrontOffice\Model\Table
 */
class AnrTable extends AbstractEntityTable
{
    public function __construct(DbCli $dbService, ConnectedUserService $connectedUserService)
    {
        parent::__construct($dbService, Anr::class, $connectedUserService);
    }

    /**
     * @throws EntityNotFoundException
     */
    public function findById(int $id): Anr
    {
        /** @var Anr|null $anr */
        $anr = $this->getRepository()->find($id);
        if ($anr === null) {
            throw new EntityNotFoundException(sprintf('Anr with id "%d" was not found', $id));
        }

        return $anr;
    }

    /**
     * @param int[] $anrIds
     *
     * @return Anr[]
     */
    public function findByIds(array $ids): array
    {
        $queryBuilder = $this->getRepository()->createQueryBuilder('a');

        return $queryBuilder
            ->where($queryBuilder->expr()->in('a.id', $ids))
            ->getQuery()
            ->getResult();
    }

    /**
     * @param string $uuid
     *
     * @return Anr|null
     * @throws NonUniqueResultException
     */
    public function findByUuid(string $uuid): ?Anr
    {
        return $this->getRepository()->createQueryBuilder('a')
            ->where('a.uuid = :uuid')
            ->setParameter('uuid', $uuid)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @param string[] $uuids
     *
     * @return Anr[]
     */
    public function findByUuids(array $uuids): array
    {
        $queryBuilder = $this->getRepository()->createQueryBuilder('a');

        return $queryBuilder
            ->where($queryBuilder->expr()->in('a.uuid', $uuids))
            ->getQuery()
            ->getResult();
    }

    public function fetchAnrsExcludeSnapshotsWithUserRights(int $userId): array
    {
        $excludedSnapshots = $this->getSnapshotsIdsQueryBuilder();

        $queryBuilder = $this->getRepository()->createQueryBuilder('a');

        return $queryBuilder
            ->select('a.id, a.label1, a.label2, a.label3, a.label4, (CASE WHEN ua.rwd IS NULL THEN -1 ELSE ua.rwd END) AS rwd')
            ->leftJoin(UserAnr::class, 'ua', Expr\Join::WITH, 'ua.anr = a AND ua.user = :userId')
            ->where($queryBuilder->expr()->notIn('a.id', $excludedSnapshots->getDQL()))
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * @return Anr[]
     */
    public function findAll(): array
    {
        return $this->getRepository()->findAll();
    }

    /**
     * @return Anr[]
     */
    public function findAllExcludeSnapshots(): array
    {
        $excludedSnapshots = $this->getSnapshotsIdsQueryBuilder();

        $queryBuilder = $this->getRepository()->createQueryBuilder('a');

        return $queryBuilder
            ->where($queryBuilder->expr()->notIn('a.id', $excludedSnapshots->getDQL()))
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Anr[]
     */
    public function findVisibleOnDashboard(): array
    {
        return $this
            ->getRepository()
            ->createQueryBuilder('a')
            ->where('a.isVisibleOnDashboard = 1')
            ->getQuery()
            ->getResult();
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function saveEntity(AnrSuperClass $anr, bool $flushAll = true): void
    {
        $em = $this->getDb()->getEntityManager();
        $em->persist($anr);
        if ($flushAll) {
            $em->flush();
        }
    }

    public function deleteEntity(AnrSuperClass $anr, bool $flushAll = true): void
    {
        $em = $this->getDb()->getEntityManager();
        $em->remove($anr);
        if ($flushAll) {
            $em->flush();
        }
    }

    private function getSnapshotsIdsQueryBuilder(): QueryBuilder
    {
        return $this->getDb()
            ->getEntityManager()
            ->createQueryBuilder()
            ->select('anr.id')
            ->from(Snapshot::class, 's')
            ->join(Anr::class, 'anr', Expr\Join::WITH, 's.anr = anr');
    }
}
