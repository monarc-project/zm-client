<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Model\Table;

use Doctrine\ORM\EntityManager;
use Monarc\FrontOffice\Model\Entity\OperationalInstanceRiskScale;
use Monarc\Core\Model\Table\AbstractTable;
use Monarc\FrontOffice\Model\Entity\Anr;

class OperationalInstanceRiskScaleTable extends AbstractTable
{
    public function __construct(EntityManager $entityManager)
    {
        parent::__construct($entityManager, OperationalInstanceRiskScale::class);
    }

    public function isRisksEvaluationStartedForAnr(Anr $anr): bool
    {
        $queryBuilder = $this->getRepository()->createQueryBuilder('oirs');
        $result = $queryBuilder
            ->where('oirs.anr = :anr')
            ->andWhere($queryBuilder->expr()->orX(
                $queryBuilder->expr()->neq('oirs.brutValue', -1),
                $queryBuilder->expr()->neq('oirs.netValue', -1),
                $queryBuilder->expr()->neq('oirs.targetedValue', -1),
            ))
            ->setParameter('anr', $anr)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $result !== null;
    }
}
