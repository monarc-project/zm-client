<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Model\Table;

use Doctrine\ORM\EntityNotFoundException;
use Monarc\Core\Model\Entity\AnrSuperClass;
use Monarc\Core\Model\Entity\AssetSuperClass;
use Monarc\Core\Model\Entity\ObjectCategorySuperClass;
use Monarc\Core\Model\Entity\ObjectSuperClass;
use Monarc\Core\Model\Entity\RolfTagSuperClass;
use Monarc\Core\Model\Table\MonarcObjectTable as CoreMonarcObjectTable;
use Monarc\FrontOffice\Model\DbCli;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Model\Entity\MonarcObject;

/**
 * Class MonarcObjectTable
 * @package Monarc\FrontOffice\Model\Table
 */
class MonarcObjectTable extends CoreMonarcObjectTable
{
    public function __construct(DbCli $dbService, ConnectedUserService $connectedUserService)
    {
        parent::__construct($dbService, $connectedUserService);
    }

    public function getEntityClass(): string
    {
        return MonarcObject::class;
    }

    public function findByAnrAndUuid(AnrSuperClass $anr, string $uuid): MonarcObject
    {
        $monarcObject = $this->getRepository()
            ->createQueryBuilder('mo')
            ->where('mo.anr = :anr')
            ->andWhere('mo.uuid = :uuid')
            ->setParameter('anr', $anr)
            ->setParameter('uuid', $uuid)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($monarcObject === null) {
            throw EntityNotFoundException::fromClassNameAndIdentifier(\get_class($this), [$anr->getId(), $uuid]);
        }

        return $monarcObject;
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
