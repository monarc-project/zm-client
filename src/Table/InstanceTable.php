<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Table;

use DateTime;
use Doctrine\ORM\EntityManager;
use Monarc\Core\Model\Entity\AnrSuperClass;
use Monarc\Core\Model\Entity\ObjectSuperClass;
use Monarc\Core\Table\InstanceTable as CoreInstanceTable;
use Monarc\FrontOffice\Model\Entity\Anr;
use Monarc\FrontOffice\Model\Entity\Asset;
use Monarc\FrontOffice\Model\Entity\Instance;
use Monarc\FrontOffice\Model\Entity\MonarcObject;

class InstanceTable extends CoreInstanceTable
{
    public function __construct(EntityManager $entityManager, string $entityName = Instance::class)
    {
        parent::__construct($entityManager, $entityName);
    }

    /**
     * @return Instance[]
     */
    public function findByAnrAndObject(AnrSuperClass $anr, ObjectSuperClass $object): array
    {
        return $this->getRepository()
            ->createQueryBuilder('i')
            ->innerJoin('i.object', 'o')
            ->where('i.anr = :anr')
            ->andWhere('o.uuid = :objUuid')
            ->andWhere('o.anr = :objAnr')
            ->setParameter('anr', $anr)
            ->setParameter('objUuid', $object->getUuid())
            ->setParameter('objAnr', $object->getAnr())
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Instance[]
     */
    public function findByAnrAssetAndObjectExcludeInstance(
        Anr $anr,
        Asset $asset,
        MonarcObject $object,
        Instance $instanceToExclude
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

    public function countByAnrIdFromDate(int $anrId, DateTime $fromDate): int
    {
        return (int)$this->getRepository()->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->where('i.anr = :anrId')
            ->andWhere('i.createdAt >= :fromDate')
            ->setParameter(':anrId', $anrId)
            ->setParameter(':fromDate', $fromDate)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return Instance[]
     */
    public function findGlobalSiblingsByAnrAndInstance(Anr $anr, Instance $instance): array
    {
        return $this->getRepository()
            ->createQueryBuilder('i')
            ->innerJoin('i.object', 'o')
            ->where('i.anr = :anr')
            ->andWhere('o.uuid = :object_uuid')
            ->andWhere('o.anr = :anr')
            ->andWhere('i.id != :id')
            ->andWhere('o.scope = ' . ObjectSuperClass::SCOPE_GLOBAL)
            ->setParameter('anr', $anr)
            ->setParameter('id', $instance->getId())
            ->setParameter('object_uuid', $instance->getObject()->getUuid())
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Instance[]
     */
    public function findInstancesByAnrWithEvaluationAndNotInheritedOrderBy(Anr $anr, array $orderBy = []): array
    {
        $queryBuilder = $this->getRepository()->createQueryBuilder('i');

        $queryBuilder
            ->where('i.anr = :anr')
            ->andWhere($queryBuilder->expr()->orX(
                $queryBuilder->expr()->andX(
                    $queryBuilder->expr()->gt('i.c', -1),
                    $queryBuilder->expr()->eq('i.ch', 0),
                ),
                $queryBuilder->expr()->andX(
                    $queryBuilder->expr()->gt('i.i', -1),
                    $queryBuilder->expr()->eq('i.ih', 0),
                ),
                $queryBuilder->expr()->andX(
                    $queryBuilder->expr()->gt('i.d', -1),
                    $queryBuilder->expr()->eq('i.dh', 0),
                )
            ))->setParameter('anr', $anr);

        foreach ($orderBy as $fieldName => $order) {
            $queryBuilder->addOrderBy($fieldName, $order);
        }

        return $queryBuilder->getQuery()->getResult();
    }
}
