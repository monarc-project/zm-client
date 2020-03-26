<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Model\Table;

use Doctrine\ORM\EntityNotFoundException;
use Monarc\Core\Model\Entity\AmvSuperClass;
use Monarc\FrontOffice\Model\DbCli;
use Monarc\Core\Model\Table\AbstractEntityTable;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Model\Entity\Amv;

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
     * @throws EntityNotFoundException
     */
    public function findByUuidAndAnrId(string $uuid, int $anrId)
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
            ->andWhere('a.uuid = :asset_uuid')
            ->andWhere('t.uuid = :threat_uuid')
            ->andWhere('v.uuid = :vulnerability_uuid')
            ->setParameter('asset_uuid', $assetUuid)
            ->setParameter('threat_uuid', $threatUuid)
            ->setParameter('vulnerability_uuid', $vulnerabilityUuid);

        if ($anrId !== null) {
            $queryBuilder
                ->andWhere('amv.anr = :anrId')
                ->setParameter('anrId', $anrId)
                ->andWhere('a.anr = :asset_anr')
                ->andWhere('t.anr = :threat_anr')
                ->andWhere('v.anr = :vulnerability_anr')
                ->setParameter('asset_anr', $anrId)
                ->setParameter('threat_anr', $anrId)
                ->setParameter('vulnerability_anr', $anrId);
        }

        return $queryBuilder
            ->getQuery()
            ->getOneOrNullResult();
    }
}
