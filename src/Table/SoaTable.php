<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Table;

use Doctrine\ORM\EntityManager;
use Monarc\Core\Table\AbstractTable;
use Monarc\FrontOffice\Entity;

class SoaTable extends AbstractTable
{
    public function __construct(EntityManager $entityManager, string $entityName = Entity\Soa::class)
    {
        parent::__construct($entityManager, $entityName);
    }

    /**
     * @return Entity\Soa[]
     */
    public function findByAnrAndSoaCategory(Entity\Anr $anr, Entity\SoaCategory $soaCategory, array $order = []): array
    {
        $queryBuilder = $this->getRepository()
            ->createQueryBuilder('s')
            ->innerJoin('s.measure', 'm')
            ->where('s.anr = :anr')
            ->andWhere('m.category = :category')
            ->setParameter('anr', $anr)
            ->setParameter('category', $soaCategory);

        foreach ($order as $filed => $direction) {
            $queryBuilder->addOrderBy($filed, $direction);
        }

        return $queryBuilder->getQuery()->getResult();
    }

    public function findByAnrAndMeasureUuid(Entity\Anr $anr, string $measureUuid): ?Entity\Soa
    {
        return $this->getRepository()
            ->createQueryBuilder('s')
            ->innerJoin('s.measure', 'm')
            ->where('s.anr = :anr')
            ->andWhere('m.uuid = :measure_uuid')
            ->andWhere('m.anr = :anr')
            ->setParameter('anr', $anr)
            ->setParameter('measure_uuid', $measureUuid)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
