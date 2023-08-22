<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2023 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Table;

use Doctrine\ORM\EntityManager;
use Monarc\Core\Model\Entity\AnrSuperClass;
use Monarc\Core\Model\Entity\AssetSuperClass;
use Monarc\Core\Model\Entity\ObjectCategorySuperClass;
use Monarc\Core\Model\Entity\ObjectSuperClass;
use Monarc\Core\Model\Entity\RolfTagSuperClass;
use Monarc\Core\Table\AbstractTable;
use Monarc\FrontOffice\Model\Entity\MonarcObject;

class MonarcObjectTable extends AbstractTable
{
    public function __construct(EntityManager $entityManager, string $entityName = MonarcObject::class)
    {
        parent::__construct($entityManager, $entityName);
    }

    public function findOneByAnrAssetNameScopeAndCategory(
        AnrSuperClass $anr,
        string $nameKey,
        string $nameValue,
        AssetSuperClass $asset,
        int $scope,
        ObjectCategorySuperClass $category
    ): ?MonarcObject {
        return $this->getRepository()
            ->createQueryBuilder('mo')
            ->innerJoin('mo.asset', 'a')
            ->where('mo.anr = :anr')
            ->andWhere('a.uuid = :assetUuid')
            ->andWhere('a.anr = :assetAnr')
            ->andWhere('mo.' . $nameKey . ' = :name')
            ->andWhere('mo.scope = :scope')
            ->andWhere('mo.category = :category')
            ->setParameter('anr', $anr)
            ->setParameter('assetUuid', $asset->getUuid())
            ->setParameter('assetAnr', $anr)
            ->setParameter('name', $nameValue)
            ->setParameter('scope', $scope)
            ->setParameter('category', $category)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findOneByAnrAndName(
        AnrSuperClass $anr,
        string $nameKey,
        string $nameValue
    ): ?MonarcObject {
        return $this->getRepository()
            ->createQueryBuilder('mo')
            ->where('mo.anr = :anr')
            ->andWhere('mo.' . $nameKey . ' = :name')
            ->setParameter('anr', $anr)
            ->setParameter('name', $nameValue)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return ObjectSuperClass[]
     */
    public function findByAnrAndRolfTag(AnrSuperClass $anr, RolfTagSuperClass $rolfTag): array
    {
        return $this->getRepository()
            ->createQueryBuilder('o')
            ->where('o.anr = :anr')
            ->setParameter('anr', $anr)
            ->andWhere('o.rolfTag = :rolfTag')
            ->setParameter('rolfTag', $rolfTag)
            ->getQuery()
            ->getResult();
    }
}
