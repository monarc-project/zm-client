<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Model\Table;

use Doctrine\ORM\EntityNotFoundException;
use Monarc\Core\Model\Entity\ThreatSuperClass;
use Monarc\Core\Model\Table\AbstractEntityTable;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Model\DbCli;
use Monarc\FrontOffice\Model\Entity\Anr;
use Monarc\FrontOffice\Model\Entity\Threat;

/**
 * Class ThreatTable
 * @package Monarc\FrontOffice\Model\Table
 */
class ThreatTable extends AbstractEntityTable
{
    public function __construct(DbCli $dbService, ConnectedUserService $connectedUserService)
    {
        parent::__construct($dbService, Threat::class, $connectedUserService);
    }

    /**
     * @param $anrId
     * @return bool
     */
    public function started($anrId)
    {
        $qb = $this->getRepository()->createQueryBuilder('t');
        $res = $qb->select('COUNT(t.uuid)')
            ->where('t.anr = :anrid')
            ->setParameter(':anrid', $anrId)
            ->andWhere('t.qualification != -1')->getQuery()->getSingleScalarResult();
        return $res > 0;
    }


    /**
     * @return Threat[]
     */
    public function findByAnr(Anr $anr)
    {
        return $this->getRepository()
            ->createQueryBuilder('t')
            ->where('t.anr = :anr')
            ->setParameter(':anr', $anr)
            ->getQuery()
            ->getResult();
    }

    public function findUuidsAndCodesByAnr(Anr $anr): array
    {
        return $this->getRepository()->createQueryBuilder('t')
            ->select('t.uuid, t.code')
            ->where('t.anr = :anr')
            ->setParameter('anr', $anr)
            ->getQuery()
            ->getScalarResult();
    }

    /**
     * @throws EntityNotFoundException
     */
    public function findByAnrAndUuid(Anr $anr, string $uuid): ThreatSuperClass
    {
        $threat = $this->getRepository()
            ->createQueryBuilder('t')
            ->where('t.anr = :anr')
            ->andWhere('t.uuid = :uuid')
            ->setParameter(':anr', $anr)
            ->setParameter(':uuid', $uuid)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($threat === null) {
            throw new EntityNotFoundException(
                sprintf('Threat with anr ID "%d" and uuid "%s" has not been found.', $anr->getId(), $uuid)
            );
        }

        return $threat;
    }

    /**
     * @param Anr $anr
     * @param string[] $uuids
     *
     * @return array
     */
    public function findByAnrAndUuidsIndexedByField(Anr $anr, array $uuids, string $indexField = 'uuid'): array
    {
        $queryBuilder = $this->getRepository()->createQueryBuilder('t', 't.' . $indexField);

        return $queryBuilder->where('t.anr = :anr')
            ->andWhere($queryBuilder->expr()->in('t.uuid', $uuids))
            ->setParameter(':anr', $anr)
            ->getQuery()
            ->getResult();
    }

    public function saveEntity(ThreatSuperClass $threat, bool $flushAll = true): void
    {
        $em = $this->getDb()->getEntityManager();
        $em->persist($threat);
        if ($flushAll) {
            $em->flush();
        }
    }
}
