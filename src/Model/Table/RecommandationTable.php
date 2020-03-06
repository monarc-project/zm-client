<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Model\Table;

use LogicException;
use Monarc\Core\Model\Entity\AnrSuperClass;
use Monarc\Core\Model\Table\AbstractEntityTable;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Model\DbCli;
use Monarc\FrontOffice\Model\Entity\Anr;
use Monarc\FrontOffice\Model\Entity\InstanceRisk;
use Monarc\FrontOffice\Model\Entity\InstanceRiskOp;
use Monarc\FrontOffice\Model\Entity\Recommandation;

/**
 * Class RecommandationTable
 * @package Monarc\FrontOffice\Model\Table
 */
class RecommandationTable extends AbstractEntityTable
{
    public function __construct(DbCli $dbService, ConnectedUserService $connectedUserService)
    {
        parent::__construct($dbService, Recommandation::class, $connectedUserService);
    }

    public function getMaxPositionByAnr(AnrSuperClass $anr): int
    {
        return (int)$this->getRepository()
            ->createQueryBuilder('r')
            ->select('MAX(r.position)')
            ->where('r.anr = :anr')
            ->setParameter('anr', $anr)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return Recommandation[]
     */
    public function findByAnrWithEmptyPosition(Anr $anr)
    {
        return $this->getRepository()
            ->createQueryBuilder('r')
            ->where('r.anr = :anr')
            ->andWhere('r.position IS NULL')
            ->setParameter('anr', $anr)
            ->getQuery()
            ->getResult();
    }

    /**
     * @param AnrSuperClass $anr
     * @param InstanceRisk|InstanceRiskOp $instanceRisk
     * @param array $order
     *
     * @return Recommandation[]
     *
     */
    public function findLinkedWithRisksByAnrExcludeInstanceRisk(
        AnrSuperClass $anr,
        $instanceRisk,
        array $order = []
    ) {
        $queryBuilder = $this->getRepository()
            ->createQueryBuilder('r')
            ->innerJoin('r.recommendationRisks', 'rr')
            ->where('r.anr = :anr')
            ->setParameter('anr', $anr);

        if ($instanceRisk instanceof InstanceRisk) {
            $queryBuilder->andWhere('rr.instanceRisk != :instanceRisk');
        } elseif ($instanceRisk instanceof InstanceRiskOp) {
            $queryBuilder->andWhere('rr.instanceRiskOp != :instanceRisk');
        } else {
            throw new LogicException('Wrong parameter type passed to the method: ' . __CLASS__ . '::' . __METHOD__);
        }
        $queryBuilder->setParameter('instanceRisk', $instanceRisk);

        foreach ($order as $field => $direction) {
            $queryBuilder->orderBy('r.' . $field, $direction);
        }

        return $queryBuilder
            ->groupBy('r.uuid')
            ->getQuery()
            ->getResult();
    }

    public function saveEntity(Recommandation $recommendation, bool $flushAll = true): void
    {
        $em = $this->getDb()->getEntityManager();
        $em->persist($recommendation);
        if ($flushAll) {
            $em->flush();
        }
    }
}
