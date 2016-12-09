<?php
namespace MonarcFO\Model\Table;

use MonarcCore\Model\Table\InstanceRiskOpTable;

class InstanceRiskOpTable extends InstanceRiskOpTable   {
    public function __construct(\MonarcCore\Model\Db $dbService) {
        parent::__construct($dbService, '\MonarcFO\Model\Entity\InstanceRiskOp');
    }

    public function started($anrId){
        $qb = $this->getRepository()->createQueryBuilder('t');
        $res = $qb->select('COUNT(t.id)')
            ->where('t.anr = :anrid')
            ->setParameter(':anrid',$anrId)
            ->andWhere($qb->expr()->orX(
                $qb->expr()->eq('t.brutProb', -1),
                $qb->expr()->eq('t.brutR', -1),
                $qb->expr()->eq('t.brutO', -1),
                $qb->expr()->eq('t.brutL', -1),
                $qb->expr()->eq('t.brutF', -1),
                $qb->expr()->eq('t.netProb', -1),
                $qb->expr()->eq('t.netR', -1),
                $qb->expr()->eq('t.netO', -1),
                $qb->expr()->eq('t.netL', -1),
                $qb->expr()->eq('t.netF', -1),
                $qb->expr()->eq('t.targetedProb', -1),
                $qb->expr()->eq('t.targetedR', -1),
                $qb->expr()->eq('t.targetedO', -1),
                $qb->expr()->eq('t.targetedL', -1),
                $qb->expr()->eq('t.targetedF', -1)
            ))->getQuery()->getSingleScalarResult();
        return $res > 0;
    }
}