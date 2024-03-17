<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2021 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Table;

use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityManager;
use Monarc\Core\Table\OperationalInstanceRiskScaleTable as CoreOperationalInstanceRiskScaleTable;
use Monarc\FrontOffice\Entity\OperationalInstanceRiskScale;
use Monarc\FrontOffice\Entity\Anr;

class OperationalInstanceRiskScaleTable extends CoreOperationalInstanceRiskScaleTable
{
    public function __construct(EntityManager $entityManager, string $entityName = OperationalInstanceRiskScale::class)
    {
        parent::__construct($entityManager, $entityName);
    }

    public function isEvaluationStarted(Anr $anr): bool
    {
        $queryBuilder = $this->getRepository()->createQueryBuilder('oirs');

        return $queryBuilder
            ->where('oirs.anr = :anr')
            ->andWhere($queryBuilder->expr()->orX(
                $queryBuilder->expr()->neq('oirs.brutValue', -1),
                $queryBuilder->expr()->neq('oirs.netValue', -1),
                $queryBuilder->expr()->neq('oirs.targetedValue', -1),
            ))
            ->setParameter('anr', $anr)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult(AbstractQuery::HYDRATE_SIMPLEOBJECT) !== null;
    }
}
