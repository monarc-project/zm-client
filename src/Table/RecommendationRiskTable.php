<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2023 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Table;

use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityManager;
use Monarc\Core\Model\Entity\AnrSuperClass;
use Monarc\Core\Table\AbstractTable;
use Monarc\FrontOffice\Model\Entity\Anr;
use Monarc\FrontOffice\Model\Entity\InstanceRisk;
use Monarc\FrontOffice\Model\Entity\InstanceRiskOp;
use Monarc\FrontOffice\Model\Entity\Recommendation;
use Monarc\FrontOffice\Model\Entity\RecommendationRisk;

class RecommendationRiskTable extends AbstractTable
{
    public function __construct(EntityManager $entityManager, string $entityName = RecommendationRisk::class)
    {
        parent::__construct($entityManager, $entityName);
    }

    /**
     * @return RecommendationRisk[]
     */
    public function findByAnrOrderByAndCanExcludeNotTreated(
        AnrSuperClass $anr,
        array $order = [],
        bool $excludeNotTreated = true
    ): array {
        $queryBuilder = $this->getRepository()
            ->createQueryBuilder('rr')
            ->innerJoin('rr.recommendation', 'r')
            ->andWhere('rr.anr = :anr')
            ->setParameter('anr', $anr);

        if ($excludeNotTreated) {
            $queryBuilder
                ->addSelect('ir, irop')
                ->leftJoin('rr.instanceRisk', 'ir')
                ->leftJoin('rr.instanceRiskOp', 'irop');
        }

        foreach ($order as $field => $ascendancy) {
            $queryBuilder->addOrderBy($field, $ascendancy);
        }

        return $queryBuilder
            ->getQuery()
            ->getResult();
    }

    public function findByInstanceRiskAndRecommendation(
        InstanceRisk $instanceRisk,
        Recommendation $recommendation
    ): ?RecommendationRisk {
        return $this->getRepository()
            ->createQueryBuilder('rr')
            ->innerJoin('rr.recommendation', 'r')
            ->where('rr.instanceRisk = :instanceRisk')
            ->andWhere('r.uuid = :recommendationUuid')
            ->andWhere('r.anr = :recommendationAnr')
            ->setParameter('instanceRisk', $instanceRisk)
            ->setParameter('recommendationUuid', $recommendation->getUuid())
            ->setParameter('recommendationAnr', $recommendation->getAnr())
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return RecommendationRisk[]
     */
    public function findAllLinkedByRecommendationGlobalObjectAndAmv(RecommendationRisk $recommendationRisk)
    {
        return $this->getRepository()
            ->createQueryBuilder('rr')
            ->innerJoin('rr.recommendation', 'r')
            ->innerJoin('rr.globalObject', 'go')
            ->innerJoin('rr.asset', 'a')
            ->innerJoin('rr.threat', 't')
            ->innerJoin('rr.vulnerability', 'v')
            ->where('rr.anr = :anr')
            ->andWhere('r.uuid = :recommendation_uuid')
            ->andWhere('r.anr = :recommendation_anr')
            ->andWhere('go.uuid = :global_object_uuid')
            ->andWhere('go.anr = :global_object_anr')
            ->andWhere('a.uuid = :asset_uuid')
            ->andWhere('a.anr = :asset_anr')
            ->andWhere('t.uuid = :threat_uuid')
            ->andWhere('t.anr = :threat_anr')
            ->andWhere('v.uuid = :vulnerability_uuid')
            ->andWhere('v.anr = :vulnerability_anr')
            ->setParameter('anr', $recommendationRisk->getAnr())
            ->setParameter('recommendation_uuid', $recommendationRisk->getRecommendation()->getUuid())
            ->setParameter('recommendation_anr', $recommendationRisk->getRecommendation()->getAnr())
            ->setParameter('global_object_uuid', $recommendationRisk->getGlobalObject()->getUuid())
            ->setParameter('global_object_anr', $recommendationRisk->getGlobalObject()->getAnr())
            ->setParameter('asset_uuid', $recommendationRisk->getAsset()->getUuid())
            ->setParameter('asset_anr', $recommendationRisk->getAsset()->getAnr())
            ->setParameter('threat_uuid', $recommendationRisk->getThreat()->getUuid())
            ->setParameter('threat_anr', $recommendationRisk->getThreat()->getAnr())
            ->setParameter('vulnerability_uuid', $recommendationRisk->getVulnerability()->getUuid())
            ->setParameter('vulnerability_anr', $recommendationRisk->getVulnerability()->getAnr())
            ->getQuery()
            ->getResult();
    }

    public function existsWithAnrRecommendationAndInstanceRisk(
        Anr $anr,
        Recommendation $recommendation,
        InstanceRisk $instanceRisk
    ): bool {
        return (bool)$this->getRepository()->createQueryBuilder('ir')
            ->where('ir.anr = :anr')
            ->andWhere('ir.recommendation = :recommendation')
            ->andWhere('ir.instanceRisk = :instanceRisk')
            ->setParameter('anr', $anr)
            ->setParameter('recommendation', $recommendation)
            ->setParameter('instanceRisk', $instanceRisk)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult(AbstractQuery::HYDRATE_SIMPLEOBJECT);
    }

    public function existsWithAnrRecommendationAndInstanceRiskOp(
        Anr $anr,
        Recommendation $recommendation,
        InstanceRiskOp $instanceRiskOp
    ): bool {
        return (bool)$this->getRepository()->createQueryBuilder('ir')
            ->where('ir.anr = :anr')
            ->andWhere('ir.recommendation = :recommendation')
            ->andWhere('ir.instanceRiskOp = :instanceRiskOp')
            ->setParameter('anr', $anr)
            ->setParameter('recommendation', $recommendation)
            ->setParameter('instanceRiskOp', $instanceRiskOp)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult(AbstractQuery::HYDRATE_SIMPLEOBJECT);
    }
}
