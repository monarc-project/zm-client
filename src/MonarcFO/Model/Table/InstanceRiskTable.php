<?php
namespace MonarcFO\Model\Table;

use MonarcCore\Model\Table\AbstractEntityTable;

class InstanceRiskTable extends AbstractEntityTable   {
    public function __construct(\MonarcCore\Model\Db $dbService) {
        parent::__construct($dbService, '\MonarcFO\Model\Entity\InstanceRisk');
    }

    public function started($anrId){
        $qb = $this->getRepository()->createQueryBuilder('t');
        $res = $qb->select('COUNT(t.id)')
            ->where('t.anr = :anrid')
            ->setParameter(':anrid',$anrId)
            ->andWhere($qb->expr()->orX(
                $qb->expr()->eq('t.threatRate', -1),
                $qb->expr()->eq('t.vulnerabilityRate', -1)
            ))->getQuery()->getSingleScalarResult();
        return $res > 0;
    }
}