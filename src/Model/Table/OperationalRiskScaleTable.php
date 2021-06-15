<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Model\Table;

use Doctrine\ORM\EntityManager;
use Monarc\Core\Model\Table\AbstractTable;
use Monarc\FrontOffice\Model\Entity\Anr;
use Monarc\FrontOffice\Model\Entity\OperationalRiskScale;

class OperationalRiskScaleTable extends AbstractTable
{
    public function __construct(EntityManager $entityManager)
    {
        parent::__construct($entityManager, OperationalRiskScale::class);
    }

    /**
     * @return OperationalRiskScale[]
     */
    public function findWithCommentsByAnr(Anr $anr): array
    {
        return $this->getRepository()->createQueryBuilder('ors')
            ->innerJoin('ors.operationalRiskScaleComments', 'orsc')
            ->where('ors.anr = :anr')
            ->setParameter('anr', $anr)
            ->getQuery()
            ->getResult();
    }

    public function getEntityById(int $id)
    {
      return parent::getEntityById(OperationalRiskScale::class, $id);
    }
}
