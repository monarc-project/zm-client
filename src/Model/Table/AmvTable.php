<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Model\Table;

use Doctrine\ORM\EntityNotFoundException;
use Monarc\Core\Model\Entity\AmvSuperClass;
use Monarc\Core\Model\Entity\AssetSuperClass;
use Monarc\FrontOffice\Model\DbCli;
use Monarc\Core\Model\Table\AbstractEntityTable;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Model\Entity\Amv;
use Monarc\FrontOffice\Model\Entity\Anr;

/**
 * Class AmvTable
 * @package Monarc\FrontOffice\Model\Table
 */
class AmvTable extends AbstractEntityTable
{
    /**
     * AmvTable constructor.
     * @param DbCli $dbService
     */
    public function __construct(DbCli $dbService, ConnectedUserService $connectedUserService)
    {
        parent::__construct($dbService, Amv::class, $connectedUserService);
    }

    /**
     * @return Amv[]
     */
    public function findByAnrIndexedByUuid(Anr $anr): array
    {
        return $this->getRepository()
            ->createQueryBuilder('amv', 'amv.uuid')
            ->where('amv.anr = :anr')
            ->setParameter('anr', $anr)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Amv[]
     */
    public function findByAnrJoinAsset(Anr $anr): array
    {
        return $this->getRepository()
            ->createQueryBuilder('amv')
            ->innerJoin('amv.asset', 'a')
            ->where('amv.anr = :anr')
            ->setParameter('anr', $anr)
            ->getQuery()
            ->getResult();
    }

    /**
     * @throws EntityNotFoundException
     */
    public function findByUuidAndAnrId(string $uuid, int $anrId): Amv
    {
        $amv = $this->getRepository()
            ->createQueryBuilder('a')
            ->select('a', 'm')
            ->leftJoin('a.measures', 'm')
            ->where('a.uuid = :uuid')
            ->andWhere('a.anr = :anrId')
            ->setParameter('uuid', $uuid)
            ->setParameter('anrId', $anrId)
            ->getQuery()
            ->getOneOrNullResult();

        if ($amv === null) {
            throw new EntityNotFoundException(
                sprintf('Amv with uuid "%s" and Anr id "%d" is not found', $uuid, $anrId)
            );
        }

        return $amv;
    }

    public function deleteEntity(AmvSuperClass $amv, bool $flush = true): void
    {
        $em = $this->getDb()->getEntityManager();
        $em->remove($amv);
        if ($flush) {
            $em->flush();
        }
    }

    public function saveEntity(AmvSuperClass $amv, bool $flushAll = true): void
    {
        $em = $this->getDb()->getEntityManager();
        $em->persist($amv);
        if ($flushAll) {
            $em->flush();
        }
    }

    /**
     * Called from Core/AmvService.
     */
    public function findByAmvItemsUuidAndAnrId(
        string $assetUuid,
        string $threatUuid,
        string $vulnerabilityUuid,
        ?int $anrId = null
    ): ?AmvSuperClass {
        $queryBuilder = $this->getRepository()
            ->createQueryBuilder('amv')
            ->innerJoin('amv.asset', 'a')
            ->innerJoin('amv.threat', 't')
            ->innerJoin('amv.vulnerability', 'v')
            ->andWhere('a.uuid = :assetUuid')
            ->andWhere('t.uuid = :threatUuid')
            ->andWhere('v.uuid = :vulnerabilityUuid')
            ->setParameter('assetUuid', $assetUuid)
            ->setParameter('threatUuid', $threatUuid)
            ->setParameter('vulnerabilityUuid', $vulnerabilityUuid);

        if ($anrId !== null) {
            $queryBuilder
                ->andWhere('amv.anr = :anrId')
                ->setParameter('anrId', $anrId)
                ->andWhere('a.anr = :assetAnr')
                ->andWhere('t.anr = :threatAnr')
                ->andWhere('v.anr = :vulnerabilityAnr')
                ->setParameter('assetAnr', $anrId)
                ->setParameter('threatAnr', $anrId)
                ->setParameter('vulnerabilityAnr', $anrId);
        }

        return $queryBuilder
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * TODO: the Core AmvTable has the same method, after #240 is done and the core table is inherited, can be removed.
     * @return AmvSuperClass[]
     */
    public function findByAsset(AssetSuperClass $asset)
    {
        return $this->getRepository()
            ->createQueryBuilder('amv')
            ->innerJoin('amv.asset', 'a')
            ->where('amv.anr = :anr')
            ->andWhere('a.uuid = :assetUuid')
            ->andWhere('a.anr = :assetAnr')
            ->setParameter('anr', $asset->getAnr())
            ->setParameter('assetUuid', $asset->getUuid())
            ->setParameter('assetAnr', $asset->getAnr())
            ->getQuery()
            ->getResult();
    }

    public function deleteEntities(array $entities): void
    {
        $this->getDb()->deleteAll($entities);
    }
}
