<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Table;

use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityManager;
use Monarc\Core\Entity\InstanceRiskSuperClass;
use Monarc\Core\Entity\InstanceSuperClass;
use Monarc\Core\Table\InstanceRiskTable as CoreInstanceRiskTable;
use Monarc\FrontOffice\Entity\Amv;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Entity\Instance;
use Monarc\FrontOffice\Entity\InstanceRisk;
use Monarc\FrontOffice\Entity\Threat;
use Monarc\FrontOffice\Entity\Vulnerability;

class InstanceRiskTable extends CoreInstanceRiskTable
{
    public function __construct(EntityManager $entityManager, string $entityName = InstanceRisk::class)
    {
        parent::__construct($entityManager, $entityName);
    }

    public function isEvaluationStarted(Anr $anr): bool
    {
        $queryBuilder = $this->getRepository()->createQueryBuilder('ir');

        return $queryBuilder
            ->where('ir.anr = :anr')
            ->setParameter(':anr', $anr)
            ->andWhere($queryBuilder->expr()->orX(
                $queryBuilder->expr()->neq('ir.threatRate', -1),
                $queryBuilder->expr()->neq('ir.vulnerabilityRate', -1)
            ))
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult(AbstractQuery::HYDRATE_SIMPLEOBJECT) !== null;
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

    /**
     * @return InstanceRisk[]
     */
    public function findByAmv(Amv $amv): array
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

    public function findRisksValuesForCartoStatsByAnr(Anr $anr, $riskValueField): array
    {
        return $this->getRepository()->createQueryBuilder('ir')
            ->select([
                'ir as instanceRisk',
                'ir.kindOfMeasure as treatment',
                'IDENTITY(ir.amv) as amv',
                'IDENTITY(ir.asset) as asset',
                'IDENTITY(ir.threat) as threat',
                'IDENTITY(ir.vulnerability) as vulnerability',
                $riskValueField . ' as maximus',
                'i.c as ic',
                'i.i as ii',
                'i.d as id',
                'IDENTITY(i.object) as object',
                'm.c as mc',
                'm.i as mi',
                'm.a as ma',
                'o.scope',
            ])->where('ir.anr = :anr')
            ->setParameter(':anr', $anr)
            ->andWhere($riskValueField . " != -1")
            ->innerJoin('ir.instance', 'i')
            ->innerJoin('ir.threat', 'm')
            ->innerJoin('i.object', 'o')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return InstanceRisk[]
     */
    public function findByAnrAndOrderByParams(Anr $anr, array $orderBy = []): array
    {
        $queryBuilder = $this->getRepository()->createQueryBuilder('ir')
            ->innerJoin('ir.instance', 'i')
            ->innerJoin('ir.threat', 't')
            ->innerJoin('ir.vulnerability', 'v')
            ->innerJoin('i.object', 'o')
            ->where('ir.anr = :anr')
            ->setParameter('anr', $anr);

        foreach ($orderBy as $fieldName => $order) {
            $queryBuilder->addOrderBy($fieldName, $order);
        }

        return $queryBuilder->getQuery()->getResult();
    }

    /**
     * @return InstanceRisk[]
     */
    public function findByAnrAndThreatExcludeLocallySet(
        Anr $anr,
        Threat $threat,
        bool $excludeLocallySetThreatRates
    ): array {
        $queryBuilder = $this->getRepository()->createQueryBuilder('ir')
            ->innerJoin('ir.threat', 't')
            ->where('ir.anr = :anr')
            ->andWhere('t.uuid = :threat_uuid')
            ->andWhere('t.anr = :anr')
            ->setParameter('anr', $anr)
            ->setParameter('threat_uuid', $threat->getUuid());
        if ($excludeLocallySetThreatRates) {
            $queryBuilder->andWhere('ir.isThreatRateNotSetOrModifiedExternally = 1');
        }

        return $queryBuilder->getQuery()->getResult();
    }

    public function existsInAnrWithInstanceThreatAndVulnerability(
        Instance $instance,
        Threat $threat,
        Vulnerability $vulnerability
    ): bool {
        return (bool)$this->getRepository()->createQueryBuilder('ir')
            ->innerJoin('ir.threat', 't')
            ->innerJoin('ir.vulnerability', 'v')
            ->where('ir.anr = :anr')
            ->andWhere('ir.instance = :instance')
            ->andWhere('t.uuid = :threat_uuid')
            ->andWhere('t.anr = :anr')
            ->andWhere('v.uuid = :vulnerability_uuid')
            ->andWhere('v.anr = :anr')
            ->setParameter('anr', $instance->getAnr())
            ->setParameter('instance', $instance)
            ->setParameter('threat_uuid', $threat->getUuid())
            ->setParameter('vulnerability_uuid', $vulnerability->getUuid())
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult(AbstractQuery::HYDRATE_SIMPLEOBJECT);
    }

    /**
     * @return InstanceRisk[]
     */
    public function findSiblingSpecificInstanceRisks(InstanceRisk $instanceRisk): array
    {
        return $this->getRepository()->createQueryBuilder('ir')
            ->innerJoin('ir.asset', 'a')
            ->innerJoin('ir.threat', 't')
            ->innerJoin('ir.vulnerability', 'v')
            ->where('ir.anr = :anr')
            ->andWhere('a.uuid = :asset_uuid')
            ->andWhere('a.anr = :anr')
            ->andWhere('t.uuid = :threat_uuid')
            ->andWhere('t.anr = :anr')
            ->andWhere('v.uuid = :vulnerability_uuid')
            ->andWhere('v.anr = :anr')
            ->andWhere('ir.id != ' . $instanceRisk->getId())
            ->andWhere('ir.specific = 1')
            ->setParameter('anr', $instanceRisk->getAnr())
            ->setParameter('asset_uuid', $instanceRisk->getAsset()->getUuid())
            ->setParameter('threat_uuid', $instanceRisk->getThreat()->getUuid())
            ->setParameter('vulnerability_uuid', $instanceRisk->getVulnerability()->getUuid())
            ->getQuery()
            ->getResult();
    }
}
