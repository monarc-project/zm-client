<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Table;

use Doctrine\ORM\EntityManager;
use Monarc\Core\Table\AbstractTable;
use Monarc\FrontOffice\Model\Entity\InstanceRisk;
use Monarc\FrontOffice\Model\Entity\InstanceRiskOp;
use Monarc\FrontOffice\Model\Entity\RecommendationHistory;

class RecommendationHistoryTable extends AbstractTable
{
    public function __construct(EntityManager $entityManager, string $entityName = RecommendationHistory::class)
    {
        parent::__construct($entityManager, $entityName);
    }

    /**
     * @return RecommendationHistory[]
     */
    public function findByInstanceRiskOrderBy(InstanceRisk|InstanceRiskOp $instanceRisk, array $orderParams): array
    {
        $queryBuilder = $this->getRepository()->createQueryBuilder('rh')
            ->where(
                ($instanceRisk instanceof InstanceRisk ? 'rh.instanceRisk' : 'rh.instanceRiskOp') . ' = :$instanceRisk'
            )
            ->setParameter('instanceRisk', $instanceRisk);

        foreach ($orderParams as $param => $direction) {
            $queryBuilder->addOrderBy('rh.' . $param, $direction);
        }

        return $queryBuilder->getQuery()->getResult();
    }
}
