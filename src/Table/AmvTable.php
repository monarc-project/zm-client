<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2022 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Table;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityNotFoundException;
use Monarc\Core\Model\Entity\AmvSuperClass;
use Monarc\Core\Model\Entity\AnrSuperClass;
use Monarc\Core\Model\Entity\AssetSuperClass;
use Monarc\Core\Table\AbstractTable;
use Monarc\FrontOffice\Model\Entity\Amv;
use Monarc\FrontOffice\Model\Entity\Anr;

class AmvTable extends AbstractTable
{
    public function __construct(EntityManager $entityManager, string $entityName = Amv::class)
    {
        parent::__construct($entityManager, $entityName);
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
     * TODO: replace with the abstract one usage if possible ->select('a', 'm') ???
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

    public function findByAmvItemsUuidsAndAnr(
        string $assetUuid,
        string $threatUuid,
        string $vulnerabilityUuid,
        AnrSuperClass $anr
    ): ?AmvSuperClass {
        return $this->getRepository()
            ->createQueryBuilder('amv')
            ->innerJoin('amv.asset', 'a')
            ->innerJoin('amv.threat', 't')
            ->innerJoin('amv.vulnerability', 'v')
            ->andWhere('amv.anr = :anr')
            ->andWhere('a.uuid = :assetUuid')
            ->andWhere('a.anr = :anr')
            ->andWhere('t.uuid = :threatUuid')
            ->andWhere('t.anr = :anr')
            ->andWhere('v.uuid = :vulnerabilityUuid')
            ->andWhere('v.anr = :anr')
            ->setParameter('assetUuid', $assetUuid)
            ->setParameter('threatUuid', $threatUuid)
            ->setParameter('vulnerabilityUuid', $vulnerabilityUuid)
            ->setParameter('anr', $anr)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return AmvSuperClass[]
     */
    public function findByAsset(AssetSuperClass $asset): array
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
}
