<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Model\Table;

use Monarc\Core\Model\Entity\AnrSuperClass;
use Monarc\Core\Model\Table\MeasureTable as CoreMeasureTable;
use Monarc\FrontOffice\Model\DbCli;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Model\Entity\Anr;
use Monarc\FrontOffice\Model\Entity\Measure;

/**
 * Class MeasureTable
 * @package Monarc\FrontOffice\Model\Table
 */
class MeasureTable extends CoreMeasureTable
{
    public function __construct(DbCli $dbService, ConnectedUserService $connectedUserService)
    {
        parent::__construct($dbService, $connectedUserService);

        $this->entityClass = Measure::class;
    }

    /**
     * @return Measure[]
     */
    public function findByAnr(Anr $anr): array
    {
        return $this->getRepository()
            ->createQueryBuilder('m')
            ->where('m.anr = :anr')
            ->setParameter('anr', $anr)
            ->getQuery()
            ->getResult();
    }

    public function findByAnrAndUuid(AnrSuperClass $anr, string $uuid): ?Measure
    {
        return $this->getRepository()
            ->createQueryBuilder('m')
            ->where('m.anr = :anr')
            ->setParameter('anr', $anr)
            ->andWhere('m.uuid = :uuid')
            ->setParameter('uuid', $uuid)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
