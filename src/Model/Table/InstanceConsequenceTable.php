<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Model\Table;

use Monarc\FrontOffice\Model\DbCli;
use Monarc\Core\Model\Table\AbstractEntityTable;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Model\Entity\InstanceConsequence;

/**
 * Class InstanceConsequenceTable
 * @package Monarc\FrontOffice\Model\Table
 */
class InstanceConsequenceTable extends AbstractEntityTable
{
    public function __construct(DbCli $dbService, ConnectedUserService $connectedUserService)
    {
        parent::__construct($dbService, InstanceConsequence::class, $connectedUserService);
    }

    /**
     * @param $anrId
     * @return bool
     */
    public function started($anrId)
    {
        $qb = $this->getRepository()->createQueryBuilder('t');
        $res = $qb->select('COUNT(t.id)')
            ->innerJoin('t.instance', 'i')
            ->where('t.anr = :anrid')
            ->setParameter(':anrid', $anrId)
            ->andWhere($qb->expr()->orX(
                $qb->expr()->neq('t.c', -1),
                $qb->expr()->neq('t.i', -1),
                $qb->expr()->neq('t.d', -1),
                $qb->expr()->neq('i.c', -1),
                $qb->expr()->neq('i.i', -1),
                $qb->expr()->neq('i.d', -1)
            ))->getQuery()->getSingleScalarResult();

        return $res > 0;
    }
}
