<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Model\Table;

use Monarc\Core\Model\Table\RolfTagTable as CoreRolfTagTable;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Model\DbCli;
use Monarc\FrontOffice\Model\Entity\Anr;
use Monarc\FrontOffice\Model\Entity\RolfTag;

/**
 * Class RolfTagTable
 * @package Monarc\FrontOffice\Model\Table
 */
class RolfTagTable extends CoreRolfTagTable
{
    public function __construct(DbCli $dbService, ConnectedUserService $connectedUserService)
    {
        parent::__construct($dbService, $connectedUserService);

        $this->entityClass = RolfTag::class;
    }

    public function findByAnrAndCode(Anr $anr, string $code): ?RolfTag
    {
        return $this->getRepository()
            ->createQueryBuilder('rt')
            ->where('rt.anr = :anr')
            ->setParameter('anr', $anr)
            ->andWhere('rt.code = :code')
            ->setParameter('code', $code)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function saveEntity(RolfTag $rolfTag, bool $flushAll = true): void
    {
        $em = $this->getDb()->getEntityManager();
        $em->persist($rolfTag);
        if ($flushAll) {
            $em->flush();
        }
    }
}
