<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Model\Table;

use Doctrine\ORM\NonUniqueResultException;
use Monarc\Core\Model\Table\AbstractEntityTable;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Model\DbCli;
use Monarc\FrontOffice\Model\Entity\Anr;
use Monarc\FrontOffice\Model\Entity\Referential;

/**
 * Class ReferentialTable
 * @package Monarc\FrontOffice\Model\Table
 */
class ReferentialTable extends AbstractEntityTable
{
    public function __construct(DbCli $dbService, ConnectedUserService $connectedUserService)
    {
        parent::__construct($dbService, Referential::class, $connectedUserService);
    }

    /**
     * @return Referential[]
     */
    public function findByAnr(Anr $anr)
    {
        return $this->getRepository()
            ->createQueryBuilder('r')
            ->where('r.anr = :anr')
            ->setParameter('anr', $anr)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Referential|null
     * @throws NonUniqueResultException
     */
    public function findByAnrAndUuid(Anr $anr, string $uuid): ?Referential
    {
        return $this->getRepository()
            ->createQueryBuilder('r')
            ->where('r.anr = :anr')
            ->andWhere('r.uuid = :uuid')
            ->setParameter('anr', $anr)
            ->setParameter('uuid', $uuid)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
