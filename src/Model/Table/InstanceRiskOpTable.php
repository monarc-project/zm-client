<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Model\Table;

use Monarc\Core\Model\Table\InstanceRiskOpTable as CoreInstanceRiskOpTable;
use Monarc\FrontOffice\Model\DbCli;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Model\Entity\InstanceRiskOp;

/**
 * Class InstanceRiskOpTable
 * @package Monarc\FrontOffice\Model\Table
 */
class InstanceRiskOpTable extends CoreInstanceRiskOpTable
{
    public function __construct(DbCli $dbService, ConnectedUserService $connectedUserService)
    {
        parent::__construct($dbService, $connectedUserService);
    }

    public function getEntityClass(): string
    {
        return InstanceRiskOp::class;
    }

    /**
     * @param $anrId
     * @return bool
     */
    public function started($anrId)
    {
        $qb = $this->getRepository()->createQueryBuilder('t');
        $res = $qb->select('COUNT(t.id)')
            ->where('t.anr = :anrid')
            ->setParameter(':anrid', $anrId)
            ->andWhere($qb->expr()->orX(
                $qb->expr()->neq('t.brutProb', -1),
                $qb->expr()->neq('t.brutR', -1),
                $qb->expr()->neq('t.brutO', -1),
                $qb->expr()->neq('t.brutL', -1),
                $qb->expr()->neq('t.brutF', -1),
                $qb->expr()->neq('t.netProb', -1),
                $qb->expr()->neq('t.netR', -1),
                $qb->expr()->neq('t.netO', -1),
                $qb->expr()->neq('t.netL', -1),
                $qb->expr()->neq('t.netF', -1),
                $qb->expr()->neq('t.targetedProb', -1),
                $qb->expr()->neq('t.targetedR', -1),
                $qb->expr()->neq('t.targetedO', -1),
                $qb->expr()->neq('t.targetedL', -1),
                $qb->expr()->neq('t.targetedF', -1)
            ))->getQuery()->getSingleScalarResult();
        return $res > 0;
    }
}
