<?php
namespace MonarcFO\Model\Table;

use MonarcCore\Model\Table\AbstractEntityTable;

class InstanceConsequenceTable extends AbstractEntityTable   {
    public function __construct(\MonarcCore\Model\Db $dbService) {
        parent::__construct($dbService, '\MonarcFO\Model\Entity\InstanceConsequence');
    }


    public function started($anrId){
        $qb = $this->getRepository()->createQueryBuilder('t');
        $res = $qb->select('COUNT(t.id)')
        	->innerJoin('t.instance', 'i')
            ->where('t.anr = :anrid')
            ->setParameter(':anrid',$anrId)
            ->andWhere($qb->expr()->orX(
                $qb->expr()->eq('t.c', -1),
                $qb->expr()->eq('t.i', -1),
                $qb->expr()->eq('t.d', -1),
                $qb->expr()->eq('i.c', -1),
                $qb->expr()->eq('i.i', -1),
                $qb->expr()->eq('i.d', -1)
            ))->getQuery()->getSingleScalarResult();
        return $res > 0;
    }
}