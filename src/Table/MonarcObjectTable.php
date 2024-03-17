<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Table;

use Doctrine\ORM\EntityManager;
use Monarc\Core\Entity\AnrSuperClass;
use Monarc\Core\Entity\ObjectSuperClass;
use Monarc\Core\Entity\RolfTagSuperClass;
use Monarc\Core\Table\AbstractTable;
use Monarc\Core\Table\Interfaces\PositionUpdatableTableInterface;
use Monarc\Core\Table\Traits\PositionIncrementTableTrait;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Entity\Asset;
use Monarc\FrontOffice\Entity\MonarcObject;
use Monarc\FrontOffice\Entity\ObjectCategory;

class MonarcObjectTable extends AbstractTable implements PositionUpdatableTableInterface
{
    use PositionIncrementTableTrait;

    public function __construct(EntityManager $entityManager, string $entityName = MonarcObject::class)
    {
        parent::__construct($entityManager, $entityName);
    }

    public function findOneByAnrAssetNameScopeAndCategory(
        AnrSuperClass $anr,
        string $nameKey,
        string $nameValue,
        Asset $asset,
        int $scope,
        ObjectCategory $category
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

    public function findOneByAnrAndName(Anr $anr, string $nameKey, string $nameValue): ?MonarcObject
    {
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
     * TODO: it's called from Core\RolfRiskService, remove when the $rolfTag->getObjects() relation is added.
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
