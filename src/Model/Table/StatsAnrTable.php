<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Model\Table;

use DateTime;
use Doctrine\ORM\EntityManager;
use Monarc\FrontOffice\Model\Entity\StatsAnr;

/**
 * TODO: if we go with stats-api, stats table will be removed.
 */
class StatsAnrTable extends AbstractTable
{
    public function __construct(EntityManager $entityManager)
    {
        parent::__construct($entityManager, StatsAnr::class);
    }

    /**
     * @param DateTime $date
     *
     * @return array
     */
    public function findByDateOfCreatedAt(DateTime $dateTime): array
    {
        return $this->getRepository()
            ->createQueryBuilder('sa')
            ->where('sa.createdAt BETWEEN :from AND :to')
            ->setParameter('from', $dateTime->format('Y-m-d') . '00:00:00')
            ->setParameter('to', $dateTime->format('Y-m-d') . '23:59:59')
            ->getQuery()
            ->getResult();
    }
}
