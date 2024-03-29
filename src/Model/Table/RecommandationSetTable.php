<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Model\Table;

use Doctrine\ORM\EntityNotFoundException;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Monarc\Core\Model\Table\AbstractEntityTable;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Model\DbCli;
use Monarc\FrontOffice\Model\Entity\Anr;
use Monarc\FrontOffice\Model\Entity\RecommandationSet;

/**
 * Class RecommandationSetTable
 * @package Monarc\FrontOffice\Model\Table
 */
class RecommandationSetTable extends AbstractEntityTable
{
    public function __construct(DbCli $dbService, ConnectedUserService $connectedUserService)
    {
        parent::__construct($dbService, RecommandationSet::class, $connectedUserService);
    }

    /**
     * @throws EntityNotFoundException
     * @throws NonUniqueResultException
     */
    public function findByAnrAndUuid(Anr $anr, string $uuid): RecommandationSet
    {
        $recommendationSet = $this->getRepository()
            ->createQueryBuilder('rs')
            ->where('rs.anr = :anr')
            ->andWhere('rs.uuid = :uuid')
            ->setParameter('anr', $anr)
            ->setParameter('uuid', $uuid)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($recommendationSet === null) {
            throw new EntityNotFoundException(
                sprintf('Recommendation set with anr ID "%d" and uuid "%s" has not been found.', $anr->getId(), $uuid)
            );
        }

        return $recommendationSet;
    }

    /**
     * @return RecommandationSet[]
     */
    public function findByAnr(Anr $anr): array
    {
        return $this->getRepository()
            ->createQueryBuilder('rs')
            ->where('rs.anr = :anr')
            ->setParameter('anr', $anr)
            ->getQuery()
            ->getResult();
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function deleteEntity(RecommandationSet $recommendationSet, bool $flush = true): void
    {
        $em = $this->getDb()->getEntityManager();
        $em->remove($recommendationSet);
        if ($flush) {
            $em->flush();
        }
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function saveEntity(RecommandationSet $recommendationSet, bool $flush = true): void
    {
        $em = $this->getDb()->getEntityManager();
        $em->persist($recommendationSet);
        if ($flush) {
            $em->flush();
        }
    }
}
