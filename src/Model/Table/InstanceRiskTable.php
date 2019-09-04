<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Model\Table;

/**
 * Class InstanceRiskTable
 * @package Monarc\FrontOffice\Model\Table
 */
class InstanceRiskTable extends \Monarc\Core\Model\Table\InstanceRiskTable
{
    /**
     * InstanceRiskTable constructor.
     * @param \Monarc\Core\Model\Db $dbService
     */
    public function __construct(\Monarc\Core\Model\Db $dbService)
    {
        parent::__construct($dbService, '\Monarc\FrontOffice\Model\Entity\InstanceRisk');
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
                $qb->expr()->neq('t.threatRate', -1),
                $qb->expr()->neq('t.vulnerabilityRate', -1)
            ))->getQuery()->getSingleScalarResult();

        return $res > 0;
    }
}
