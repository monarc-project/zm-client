<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Model\Table;

use Monarc\Core\Exception\Exception;
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

    public function findByUuidAndAnrId(string $uuid, int $anrId)
    {
        $amv = $this->getRepository()
            ->createQueryBuilder('a, m')
            ->leftJoin('a.measures', 'm')
            ->where('a.uuid = :uuid')
            ->andWhere('a.anr_id = :anrId')
            ->setParameter('uuid', $uuid)
            ->setParameter('anrId', $anrId)
            ->getQuery()
            ->getSingleResult();

        if ($amv === null) {
            throw new Exception(sprintf('Amv with uuid "%s" and Anr id "%d" is not found', $uuid, $anrId), 412);
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
}
