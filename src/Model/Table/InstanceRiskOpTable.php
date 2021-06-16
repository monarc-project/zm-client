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
use Monarc\FrontOffice\Model\Entity\Anr;
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

    public function findRisksDataForStatsByAnr(Anr $anr): array
    {

        return $this->getRepository()
            ->createQueryBuilder('oprisk')
            ->select('
                oprisk.cacheNetRisk as cacheNetRisk,
                oprisk.cacheTargetedRisk as cacheTargetedRisk
            ')
            ->where('oprisk.anr = :anr')
            ->setParameter('anr', $anr)
            ->andWhere('oprisk.cacheNetRisk > -1')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return InstanceRiskOp[]
     */
    public function findByAnr(Anr $anr): array
    {
        return $this->getRepository()
            ->createQueryBuilder('oprisk')
            ->where('oprisk.anr = :anr')
            ->setParameter(':anr', $anr)
            ->getQuery()
            ->getResult();
    }
}
