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
use Monarc\FrontOffice\Model\Entity\Anr;
use Monarc\FrontOffice\Model\Entity\Recommandation;
use Monarc\FrontOffice\Model\Entity\RecommandationSet;

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
     * @return Recommandation[]
     */
    public function findLinkedWithRisksByAnrWithSpecifiedImportanceAndPositionAndExcludeRecommendations(
        AnrSuperClass $anr,
        array $excludeRecommendations = [],
        array $order = []
    ) {
        $queryBuilder = $this->getRepository()
            ->createQueryBuilder('r')
            ->innerJoin('r.recommendationRisks', 'rr')
            ->where('r.anr = :anr')
            ->andWhere('r.importance > 0')
            ->andWhere('r.position > 0')
            ->setParameter('anr', $anr);

        if (!empty($excludeRecommendations)) {
            $queryBuilder
                ->andWhere($queryBuilder->expr()->notIn('r.uuid', ':recommendations_uuid'))
                ->setParameter('recommendations_uuid', $excludeRecommendations);
        }

        foreach ($order as $field => $direction) {
            $queryBuilder->orderBy('r.' . $field, $direction);
        }

        return $queryBuilder
            ->groupBy('r.uuid')
            ->getQuery()
            ->getResult();
    }

    /**
     * @throws EntityNotFoundException
     */
    public function findByAnrAndUuid(AnrSuperClass $anr, string $uuid): Recommandation
    {
        $recommendation = $this->getRepository()
            ->createQueryBuilder('r')
            ->where('r.anr = :anr')
            ->andWhere('r.uuid = :uuid')
            ->setParameter('anr', $anr)
            ->setParameter('uuid', $uuid)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($recommendation === null) {
            throw new EntityNotFoundException(
                sprintf('Recommendation with anr ID "%d" and uuid "%s" has not been found.', $anr->getId(), $uuid)
            );
        }

        return $recommendation;
    }

    public function findByAnrCodeAndRecommendationSet(
        AnrSuperClass $anr,
        string $code,
        RecommandationSet $recommendationSet
    ): ?Recommandation {
        return $this->getRepository()
            ->createQueryBuilder('r')
            ->innerJoin('r.recommandationSet', 'rs')
            ->where('r.anr = :anr')
            ->andWhere('r.code = :code')
            ->andWhere('rs.uuid = :recommendation_set_uuid')
            ->andWhere('rs.anr = :recommendation_set_anr')
            ->setParameter('anr', $anr)
            ->setParameter('code', $code)
            ->setParameter('recommendation_set_uuid', $recommendationSet->getUuid())
            ->setParameter('recommendation_set_anr', $anr)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
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
