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
    public function findAllByAnrAndIndex(Anr $anr, int $scaleIndex): array
    {
      return $this->getRepository()->createQueryBuilder('t')
          ->where('t.anr = :anr')
          ->andWhere('t.scaleIndex = :scaleIndex')
          ->setParameter('anr', $anr)
          ->setParameter('scaleIndex', $scaleIndex)
          ->getQuery()
          ->getResult();
    }
}
