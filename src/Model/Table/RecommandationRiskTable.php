<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Model\Table;

use Doctrine\ORM\EntityNotFoundException;
use Monarc\Core\Model\Entity\AnrSuperClass;
use Monarc\Core\Model\Table\AbstractEntityTable;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Model\DbCli;
use Monarc\FrontOffice\Model\Entity\InstanceRisk;
use Monarc\FrontOffice\Model\Entity\InstanceRiskOp;
use Monarc\FrontOffice\Model\Entity\Recommandation;
use Monarc\FrontOffice\Model\Entity\RecommandationRisk;

/**
 * Class RecommandationRiskTable
 * @package Monarc\FrontOffice\Model\Table
 */
class RecommandationRiskTable extends AbstractEntityTable
{
    public function __construct(DbCli $dbService, ConnectedUserService $connectedUserService)
    {
        parent::__construct($dbService, RecommandationRisk::class, $connectedUserService);
    }

    /**
     * @throws EntityNotFoundException
     */
    public function findById(int $recommendationRiskId): RecommandationRisk
    {
        /** @var RecommandationRisk|null $recommendationRisk */
        $recommendationRisk = $this->getRepository()->find($recommendationRiskId);
        if ($recommendationRisk === null) {
            throw new EntityNotFoundException(sprintf('Anr with id "%d" was not found', $recommendationRiskId));
        }

        return $recommendationRisk;
    }

    /**
     * @return RecommandationRisk[]
     */
    public function findByAnr(AnrSuperClass $anr, array $order = [], bool $excludeNotTreated = true)
    {
        $queryBuilder = $this->getRepository()
            ->createQueryBuilder('rr')
            ->innerJoin('rr.recommandation', 'r')
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

    /**
     * @return RecommandationRisk[]
     */
    public function findByAnrAndInstanceRisk(AnrSuperClass $anr, InstanceRisk $instanceRisk)
    {
        return $this->getRepository()
            ->createQueryBuilder('rr')
            ->where('rr.anr = :anr')
            ->andWhere('rr.instanceRisk = :instanceRisk')
            ->setParameter('anr', $anr)
            ->setParameter('instanceRisk', $instanceRisk)
            ->getQuery()
            ->getResult();
    }

    public function findByInstanceRiskAndRecommendation(
        InstanceRisk $instanceRisk,
        Recommandation $recommendation
    ): ?RecommandationRisk {
        return $this->getRepository()
            ->createQueryBuilder('rr')
            ->innerJoin('rr.recommandation', 'r')
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
     * @return RecommandationRisk[]
     */
    public function findByAnrAndOperationalInstanceRisk(AnrSuperClass $anr, InstanceRiskOp $instanceRiskOp)
    {
        return $this->getRepository()
            ->createQueryBuilder('rr')
            ->where('rr.anr = :anr')
            ->andWhere('rr.instanceRiskOp = :instance_risk_op')
            ->setParameter('anr', $anr)
            ->setParameter('instance_risk_op', $instanceRiskOp)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return RecommandationRisk[]
     */
    public function findAllLinkedByRecommendationGlobalObjectAndAmv(RecommandationRisk $recommendationRisk)
    {
        return $this->getRepository()
            ->createQueryBuilder('rr')
            ->innerJoin('rr.recommandation', 'r')
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
            ->setParameter('recommendation_uuid', $recommendationRisk->getRecommandation()->getUuid())
            ->setParameter('recommendation_anr', $recommendationRisk->getRecommandation()->getAnr())
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

    public function deleteEntity(RecommandationRisk $recommendationRisk, bool $flush = true): void
    {
        $em = $this->getDb()->getEntityManager();
        $em->remove($recommendationRisk);
        if ($flush) {
            $em->flush();
        }
    }

    public function saveEntity(RecommandationRisk $recommendationRisk, bool $flush = true): void
    {
        $em = $this->getDb()->getEntityManager();
        $em->persist($recommendationRisk);
        if ($flush) {
            $em->flush();
        }
    }
}
