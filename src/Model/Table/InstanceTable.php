<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Model\Table;

use Monarc\Core\Model\Entity\AnrSuperClass;
use Monarc\Core\Model\Entity\AssetSuperClass;
use Monarc\Core\Model\Entity\InstanceSuperClass;
use Monarc\Core\Model\Entity\ObjectSuperClass;
use Monarc\Core\Model\Table\InstanceTable as CoreInstanceTable;
use Monarc\FrontOffice\Model\DbCli;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Model\Entity\Anr;
use Monarc\FrontOffice\Model\Entity\Asset;
use Monarc\FrontOffice\Model\Entity\Instance;

/**
 * Class InstanceTable
 * @package Monarc\FrontOffice\Model\Table
 */
class InstanceTable extends CoreInstanceTable
{
    public function __construct(DbCli $dbService, ConnectedUserService $connectedUserService)
    {
        parent::__construct($dbService, $connectedUserService);

        $this->entityClass = Instance::class;
    }

    /**
     * @return Instance[]
     */
    public function findByAnrAndObject(AnrSuperClass $anr, ObjectSuperClass $object): array
    {
        return $this->getRepository()
            ->createQueryBuilder('i')
            ->innerJoin('i.object', 'obj')
            ->where('i.anr = :anr')
            ->andWhere('obj.uuid = :objUuid')
            ->andWhere('obj.anr = :objAnr')
            ->setParameter('anr', $anr)
            ->setParameter('objUuid', $object->getUuid())
            ->setParameter('objAnr', $object->getAnr())
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Instance[]
     */
    public function findByAnrAndAsset(Anr $anr, Asset $asset): array
    {
        return $this->getRepository()
            ->createQueryBuilder('i')
            ->innerJoin('i.asset', 'a')
            ->where('i.anr = :anr')
            ->andWhere('a.uuid = :assetUuid')
            ->andWhere('a.anr = :anr')
            ->setParameter('anr', $anr)
            ->setParameter('assetUuid', $asset->getUuid())
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Instance[]
     */
    public function findByAnrAssetAndObjectExcludeInstance(
        AnrSuperClass $anr,
        AssetSuperClass $asset,
        ObjectSuperClass $object,
        InstanceSuperClass $instanceToExclude
    ): array {
        return $this->getRepository()
            ->createQueryBuilder('i')
            ->innerJoin('i.asset', 'asset')
            ->innerJoin('i.object', 'obj')
            ->where('i.anr = :anr')
            ->andWhere('asset.uuid = :assetUuid')
            ->andWhere('asset.anr = :assetAnr')
            ->andWhere('obj.uuid = :objUuid')
            ->andWhere('obj.anr = :objAnr')
            ->andWhere('i.id <> :instanceId')
            ->setParameter('anr', $anr)
            ->setParameter('assetUuid', $asset->getUuid())
            ->setParameter('assetAnr', $asset->getAnr())
            ->setParameter('objUuid', $object->getUuid())
            ->setParameter('objAnr', $object->getAnr())
            ->setParameter('instanceId', $instanceToExclude->getId())
            ->getQuery()
            ->getResult();
    }

    public function getMaxPositionByAnrAndParent(AnrSuperClass $anr, ?InstanceSuperClass $parentInstance = null): int
    {
        $queryBuilder = $this->getRepository()
            ->createQueryBuilder('i')
            ->select('MAX(i.position)')
            ->where('i.anr = :anr')
            ->setParameter('anr', $anr);

        if ($parentInstance !== null) {
            $queryBuilder
                ->andWhere('i.parent = :parent')
                ->setParameter('parent', $parentInstance);
        }

        return (int)$queryBuilder->getQuery()->getSingleScalarResult();
    }
}
