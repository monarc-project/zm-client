<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Model\Table;

use Monarc\Core\Model\Entity\AnrSuperClass;
use Monarc\Core\Model\Table\AbstractEntityTable;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Model\DbCli;
use Monarc\FrontOffice\Model\Entity\Scale;
use Monarc\FrontOffice\Model\Entity\ScaleImpactType;

/**
 * Class ScaleTable
 * @package Monarc\FrontOffice\Model\Table
 */
class ScaleTable extends AbstractEntityTable
{
    public function __construct(DbCli $dbService, ConnectedUserService $connectedUserService)
    {
        parent::__construct($dbService, Scale::class, $connectedUserService);
    }

    /**
     * @return Scale[]
     */
    public function findByAnr(AnrSuperClass $anr): array
    {
        return $this->getRepository()
            ->createQueryBuilder('s')
            ->where('s.anr = :anr')
            ->setParameter('anr', $anr)
            ->getQuery()
            ->getResult();
    }

    public function findByAnrAndType(AnrSuperClass $anr, int $type): ?Scale
    {
        return $this->getRepository()
            ->createQueryBuilder('s')
            ->where('s.anr = :anr')
            ->andWhere('s.type = :type')
            ->setParameter('anr', $anr)
            ->setParameter('type', $type)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
