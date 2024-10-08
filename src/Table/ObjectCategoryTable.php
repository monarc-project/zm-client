<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2023 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Table;

use Doctrine\ORM\EntityManager;
use Monarc\Core\Table\ObjectCategoryTable as CoreObjectCategoryTable;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Entity\ObjectCategory;

class ObjectCategoryTable extends CoreObjectCategoryTable
{
    public function __construct(EntityManager $entityManager, string $entityName = ObjectCategory::class)
    {
        parent::__construct($entityManager, $entityName);
    }

    /**
     * @return ObjectCategory[]
     */
    public function findRootCategoriesByAnrOrderedByPosition(Anr $anr): array
    {
        return $this->getRepository()->createQueryBuilder('oc')
            ->where('oc.anr = :anr')
            ->andWhere('oc.parent IS NULL')
            ->setParameter('anr', $anr)
            ->addOrderBy('oc.position')
            ->getQuery()
            ->getResult();
    }

    public function findByAnrParentAndLabel(
        Anr $anr,
        ?ObjectCategory $parentCategory,
        string $labelKey,
        string $labelValue
    ): ?ObjectCategory {
        $queryBuilder = $this->getRepository()->createQueryBuilder('oc')
            ->where('oc.anr = :anr')
            ->setParameter('anr', $anr);

        if ($parentCategory !== null) {
            $queryBuilder
                ->andWhere('oc.parent = :parent')
                ->setParameter('parent', $parentCategory);
        }

        return $queryBuilder
            ->andWhere('oc.' . $labelKey . ' = :' . $labelKey)
            ->setParameter($labelKey, $labelValue)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findMaxPositionByAnrAndParent(Anr $anr, ?ObjectCategory $parentObjectCategory): int
    {
        $queryBuilder = $this->getRepository()->createQueryBuilder('oc')
            ->select('MAX(oc.position)')
            ->where('oc.anr = :anr')
            ->setParameter('anr', $anr);

        if ($parentObjectCategory !== null) {
            $queryBuilder
                ->andWhere('oc.parent = :parent')
                ->setParameter('parent', $parentObjectCategory);
        } else {
            $queryBuilder->andWhere('oc.parent IS NULL');
        }

        return (int)$queryBuilder
            ->setMaxResults(1)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
