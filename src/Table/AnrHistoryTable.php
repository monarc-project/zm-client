<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Table;

use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManager;
use Monarc\Core\Table\AbstractTable;
use Monarc\FrontOffice\Entity\AnrHistory;

class AnrHistoryTable extends AbstractTable
{
    public function __construct(EntityManager $entityManager, string $entityName = AnrHistory::class)
    {
        parent::__construct($entityManager, $entityName);
    }

    /**
     * @return AnrHistory[]
     */
    public function findByAnrIdAndTypes(int $anrId, array $targetTypes = [], ?int $changeType = null): array
    {
        $queryBuilder = $this->getRepository()->createQueryBuilder('h')
            ->where('h.anrId = :anrId')
            ->setParameter('anrId', $anrId)
            ->orderBy('h.createdAt', Criteria::ASC)
            ->addOrderBy('h.id', Criteria::ASC);

        if ($targetTypes !== []) {
            $queryBuilder
                ->andWhere('h.targetType IN (:targetTypes)')
                ->setParameter('targetTypes', $targetTypes);
        }

        if ($changeType !== null) {
            $queryBuilder
                ->andWhere('h.changeType = :changeType')
                ->setParameter('changeType', $changeType);
        }

        return $queryBuilder->getQuery()->getResult();
    }

    /**
     * @return AnrHistory[]
     */
    public function findByAnrIdAndTarget(int $anrId, int $targetType, int $targetId, ?int $changeType = null): array
    {
        $queryBuilder = $this->getRepository()->createQueryBuilder('h')
            ->where('h.anrId = :anrId')
            ->andWhere('h.targetType = :targetType')
            ->andWhere('h.targetId = :targetId')
            ->setParameter('anrId', $anrId)
            ->setParameter('targetType', $targetType)
            ->setParameter('targetId', $targetId)
            ->orderBy('h.createdAt', Criteria::DESC)
            ->addOrderBy('h.id', Criteria::DESC);

        if ($changeType !== null) {
            $queryBuilder
                ->andWhere('h.changeType = :changeType')
                ->setParameter('changeType', $changeType);
        }

        return $queryBuilder->getQuery()->getResult();
    }

    public function findLatestByAnrIdAndTarget(int $anrId, int $targetType, int $targetId): ?AnrHistory
    {
        return $this->getRepository()->createQueryBuilder('h')
            ->where('h.anrId = :anrId')
            ->andWhere('h.targetType = :targetType')
            ->andWhere('h.targetId = :targetId')
            ->setParameter('anrId', $anrId)
            ->setParameter('targetType', $targetType)
            ->setParameter('targetId', $targetId)
            ->orderBy('h.createdAt', Criteria::DESC)
            ->addOrderBy('h.id', Criteria::DESC)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
