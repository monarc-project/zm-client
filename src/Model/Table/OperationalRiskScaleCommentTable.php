<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Model\Table;

use Doctrine\ORM\EntityManager;
use Monarc\FrontOffice\Model\Entity\Anr;
use Monarc\FrontOffice\Model\Entity\OperationalRiskScaleComment;
use Monarc\Core\Model\Table\AbstractTable;

class OperationalRiskScaleCommentTable extends AbstractTable
{
    public function __construct(EntityManager $entityManager)
    {
        parent::__construct($entityManager, OperationalRiskScaleComment::class);
    }

    /**
     * @return OperationalRiskScaleComment[]
     */
    public function findAllByAnrAndIndexAndScaleType(Anr $anr, int $scaleIndex, int $type): array
    {
        return $this->getRepository()->createQueryBuilder('t')
            ->innerJoin('t.operationalRiskScale', 'ors')
            ->where('t.anr = :anr')
            ->andWhere('t.scaleIndex = :scaleIndex')
            ->andWhere('ors.type = :type')
            ->setParameter('anr', $anr)
            ->setParameter('type', $type)
            ->setParameter('scaleIndex', $scaleIndex)
            ->getQuery()
            ->getResult();
    }
}
