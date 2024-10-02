<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2023 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Table;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\QueryBuilder;
use Monarc\Core\Table\AbstractTable;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Entity\Snapshot;
use Monarc\FrontOffice\Entity\UserAnr;

class AnrTable extends AbstractTable
{
    public function __construct(EntityManager $entityManager, string $entityName = Anr::class)
    {
        parent::__construct($entityManager, $entityName);
    }

    public function findOneByUuid(string $uuid): ?Anr
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
            ->select('a.id, a.label, (CASE WHEN ua.rwd IS NULL THEN -1 ELSE ua.rwd END) AS rwd')
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
        return $this->getRepository()->createQueryBuilder('a')
            ->where('a.isVisibleOnDashboard = 1')
            ->getQuery()
            ->getResult();
    }

    private function getSnapshotsIdsQueryBuilder(): QueryBuilder
    {
        return $this->entityManager->createQueryBuilder()
            ->select('anr.id')
            ->from(Snapshot::class, 's')
            ->join(Anr::class, 'anr', Expr\Join::WITH, 's.anr = anr');
    }
}
