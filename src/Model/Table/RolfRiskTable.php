<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Model\Table;

use Doctrine\ORM\EntityNotFoundException;
use Monarc\Core\Model\Table\AbstractEntityTable;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Model\DbCli;
use Monarc\FrontOffice\Model\Entity\Anr;
use Monarc\FrontOffice\Model\Entity\RolfRisk;

/**
 * Class RolfRiskTable
 * @package Monarc\FrontOffice\Model\Table
 */
class RolfRiskTable extends AbstractEntityTable
{
    public function __construct(DbCli $dbService, ConnectedUserService $connectedUserService)
    {
        parent::__construct($dbService, RolfRisk::class, $connectedUserService);
    }

    public function findById(int $id): RolfRisk
    {
        $rolfRisk = $this->getRepository()
            ->createQueryBuilder('rr')
            ->where('rr.id = :id')
            ->setParameter('id', $id)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($rolfRisk === null) {
            throw EntityNotFoundException::fromClassNameAndIdentifier(\get_class($this), [$id]);
        }
    }

    public function findByAnrAndCode(Anr $anr, string $code): ?RolfRisk
    {
        return $this->getRepository()
            ->createQueryBuilder('rr')
            ->where('rr.anr = :anr')
            ->setParameter('anr', $anr)
            ->andWhere('rr.code = :code')
            ->setParameter('code', $code)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function saveEntity(RolfRisk $rolfRisk, bool $flushAll = true): void
    {
        $em = $this->getDb()->getEntityManager();
        $em->persist($rolfRisk);
        if ($flushAll) {
            $em->flush();
        }
    }
}
