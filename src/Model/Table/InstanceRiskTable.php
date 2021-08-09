<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Model\Table;

use Monarc\Core\Model\Entity\AmvSuperClass;
use Monarc\Core\Model\Entity\AssetSuperClass;
use Monarc\Core\Model\Entity\InstanceSuperClass;
use Monarc\Core\Model\Entity\InstanceRiskSuperClass;
use Monarc\Core\Model\Table\InstanceRiskTable as CoreInstanceRiskTable;
use Monarc\FrontOffice\Model\DbCli;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Model\Entity\Anr;
use Monarc\FrontOffice\Model\Entity\InstanceRisk;

/**
 * Class InstanceRiskTable
 * @package Monarc\FrontOffice\Model\Table
 */
class InstanceRiskTable extends CoreInstanceRiskTable
{
    public function __construct(DbCli $dbService, ConnectedUserService $connectedUserService)
    {
        parent::__construct($dbService, $connectedUserService);
    }

    public function getEntityClass(): string
    {
        return InstanceRisk::class;
    }

    /**
     * @param Anr|int $anrId
     */
    public function started($anrId): bool
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

    /**
     * @return InstanceRisk[]
     */
    public function findByInstanceAndInstanceRiskRelations(
        InstanceSuperClass $instance,
        InstanceRiskSuperClass $instanceRisk,
        bool $excludeAmvFilter = false,
        bool $includeAssetFilter = false
    ) {
        $queryBuilder = $this->getRepository()
            ->createQueryBuilder('ir')
            ->where('ir.instance = :instance')
            ->setParameter('instance', $instance);

        if (!$excludeAmvFilter && $instanceRisk->getAmv() !== null) {
            $queryBuilder
                ->innerJoin('ir.amv', 'amv')
                ->andWhere('amv.uuid = :amvUuid')
                ->andWhere('amv.anr = :amvAnr')
                ->setParameter('amvUuid', $instanceRisk->getAmv()->getUuid())
                ->setParameter('amvAnr', $instanceRisk->getAmv()->getAnr());
        }
        if ($includeAssetFilter) {
            $queryBuilder
                ->innerJoin('ir.asset', 'a')
                ->andWhere('a.uuid = :assetUuid')
                ->andWhere('a.anr = :assetAnr')
                ->setParameter('assetUuid', $instanceRisk->getAsset()->getUuid())
                ->setParameter('assetAnr', $instanceRisk->getAsset()->getAnr());
        }

        $queryBuilder
            ->innerJoin('ir.threat', 'thr')
            ->innerJoin('ir.vulnerability', 'vuln')
            ->andWhere('thr.uuid = :threatUuid')
            ->andWhere('thr.anr = :threatAnr')
            ->andWhere('vuln.uuid = :vulnerabilityUuid')
            ->andWhere('vuln.anr = :vulnerabilityAnr')
            ->setParameter('threatUuid', $instanceRisk->getThreat()->getUuid())
            ->setParameter('threatAnr', $instanceRisk->getThreat()->getAnr())
            ->setParameter('vulnerabilityUuid', $instanceRisk->getVulnerability()->getUuid())
            ->setParameter('vulnerabilityAnr', $instanceRisk->getVulnerability()->getAnr());

        if ($instanceRisk->isSpecific()) {
            $queryBuilder->andWhere('ir.specific = ' . InstanceRiskSuperClass::TYPE_SPECIFIC);
        }

        return $queryBuilder->getQuery()->getResult();
    }

    public function findByInstanceAssetThreatUuidAndVulnerabilityUuid(
        InstanceSuperClass $instance,
        AssetSuperClass $asset,
        string $threatUuid,
        string $vulnerabilityUuid
    ): ?InstanceRisk {
        return $this->getRepository()
            ->createQueryBuilder('ir')
            ->innerJoin('ir.asset', 'a')
            ->innerJoin('ir.threat', 'thr')
            ->innerJoin('ir.vulnerability', 'vuln')
            ->where('ir.instance = :instance')
            ->andWhere('a.uuid = :assetUuid')
            ->andWhere('a.anr = :assetAnr')
            ->andWhere('thr.uuid = :threatUuid')
            ->andWhere('thr.anr = :threatAnr')
            ->andWhere('vuln.uuid = :vulnerabilityUuid')
            ->andWhere('vuln.anr = :vulnerabilityAnr')
            ->setParameter('instance', $instance)
            ->setParameter('assetUuid', $asset->getUuid())
            ->setParameter('assetAnr', $asset->getAnr())
            ->setParameter('threatUuid', $threatUuid)
            ->setParameter('threatAnr', $instance->getAnr())
            ->setParameter('vulnerabilityUuid', $vulnerabilityUuid)
            ->setParameter('vulnerabilityAnr', $instance->getAnr())
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return InstanceRisk[]
     */
    public function findByAmv(AmvSuperClass $amv)
    {
        return $this->getRepository()
            ->createQueryBuilder('ir')
            ->innerJoin('ir.amv', 'amv')
            ->where('amv.uuid = :amvUuid')
            ->andWhere('amv.anr = :amvAnr')
            ->setParameter('amvUuid', $amv->getUuid())
            ->setParameter('amvAnr', $amv->getAnr())
            ->getQuery()
            ->getResult();
    }

    /**
     * @return InstanceRisk[]
     */
    public function findByInstanceAndAmv(InstanceSuperClass $instance, AmvSuperClass $amv)
    {
        return $this->getRepository()
            ->createQueryBuilder('ir')
            ->innerJoin('ir.amv', 'amv')
            ->where('ir.instance = :instance')
            ->andWhere('amv.uuid = :amvUuid')
            ->andWhere('amv.anr = :amvAnr')
            ->setParameter('instance', $instance)
            ->setParameter('amvUuid', $amv->getUuid())
            ->setParameter('amvAnr', $amv->getAnr())
            ->getQuery()
            ->getResult();
    }

    public function findRisksDataForStatsByAnr(Anr $anr): array
    {
        return $this->getRepository()
            ->createQueryBuilder('ir')
            ->select('
                ir.id,
                IDENTITY(ir.asset) as assetId,
                t.uuid as threatId,
                v.uuid as vulnerabilityId,
                ir.cacheMaxRisk,
                ir.cacheTargetedRisk,
                ir.threatRate,
                ir.vulnerabilityRate,
                i.c as instanceConfidentiality,
                i.i as instanceIntegrity,
                i.d as instanceAvailability,
                t.c as threatConfidentiality,
                t.i as threatIntegrity,
                t.a as threatAvailability,
                t.label1 as threatLabel1,
                t.label2 as threatLabel2,
                t.label3 as threatLabel3,
                t.label4 as threatLabel4,
                v.label1 as vulnerabilityLabel1,
                v.label2 as vulnerabilityLabel2,
                v.label3 as vulnerabilityLabel3,
                v.label4 as vulnerabilityLabel4,
                o.scope,
                o.uuid as objectId
            ')
            ->where('ir.anr = :anr')
            ->setParameter(':anr', $anr)
            ->andWhere('ir.cacheMaxRisk > -1 OR ir.cacheTargetedRisk > -1')
            ->innerJoin('ir.instance', 'i')
            ->innerJoin('ir.threat', 't')
            ->innerJoin('ir.vulnerability', 'v')
            ->innerJoin('i.object', 'o')
            ->getQuery()
            ->getResult();
    }
}
