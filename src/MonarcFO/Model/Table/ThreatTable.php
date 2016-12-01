<?php
namespace MonarcFO\Model\Table;

use MonarcCore\Model\Table\AbstractEntityTable;

class ThreatTable extends AbstractEntityTable {
    public function __construct(\MonarcCore\Model\Db $dbService) {
        parent::__construct($dbService, '\MonarcFO\Model\Entity\Threat');
    }

    public function started($anrId){
        $qb = $this->getRepository()->createQueryBuilder('t');
        $res = $qb->select('COUNT(t.id)')
            ->where('t.anr = :anrid')
            ->setParameter(':anrid',$anrId)
            ->andWhere('t.qualification != -1')->getQuery()->getSingleScalarResult();
        return $res > 0;
    }
}