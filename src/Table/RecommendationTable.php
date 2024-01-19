<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2023 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Table;

use Doctrine\ORM\EntityManager;
use Monarc\Core\Model\Entity\AnrSuperClass;
use Monarc\Core\Table\AbstractTable;
use Monarc\Core\Table\Interfaces\PositionUpdatableTableInterface;
use Monarc\Core\Table\Traits\PositionIncrementTableTrait;
use Monarc\FrontOffice\Model\Entity\Anr;
use Monarc\FrontOffice\Model\Entity\Recommendation;
use Monarc\FrontOffice\Model\Entity\RecommendationSet;

class RecommendationTable extends AbstractTable implements PositionUpdatableTableInterface
{
    use PositionIncrementTableTrait;

    public function __construct(EntityManager $entityManager, string $entityName = Recommendation::class)
    {
        parent::__construct($entityManager, $entityName);
    }

    /**
     * @return Recommendation[]
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
     * @return Recommendation[]
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
            $queryBuilder->orderBy($field, $direction);
        }

        return $queryBuilder
            ->groupBy('r.uuid')
            ->getQuery()
            ->getResult();
    }

    public function findByAnrCodeAndRecommendationSet(
        AnrSuperClass $anr,
        string $code,
        RecommendationSet $recommendationSet
    ): ?Recommendation {
        return $this->getRepository()
            ->createQueryBuilder('r')
            ->innerJoin('r.recommendationSet', 'rs')
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

    /**
     * Returns list of recommendations which are unlinked with risks and position is not 0.
     *
     * @return Recommendation[]
     */
    public function findUnlinkedWithNotEmptyPositionByAnr(AnrSuperClass $anr)
    {
        $queryBuilderLinked = $this->getRepository()
            ->createQueryBuilder('rec')
            ->select('rec.uuid')
            ->innerJoin('rec.recommendationRisks', 'rec_risk')
            ->where('rec.anr = :anr')
            ->andWhere('rec_risk.anr = :anr')
            ->setParameter('anr', $anr)
            ->groupBy('rec.uuid');

        $queryBuilderUnlinked = $this->getRepository()->createQueryBuilder('r');

        return $queryBuilderUnlinked
            ->where('r.anr = :anr')
            ->andWhere('r.position > 0')
            ->andWhere($queryBuilderUnlinked->expr()->notIn('r.uuid', $queryBuilderLinked->getDQL()))
            ->setParameter('anr', $anr)
            ->getQuery()
            ->getResult();
    }
}
