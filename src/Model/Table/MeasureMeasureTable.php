<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Model\Table;

use Monarc\FrontOffice\Model\DbCli;
use Monarc\Core\Model\Table\AbstractEntityTable;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Model\Entity\Anr;
use Monarc\FrontOffice\Model\Entity\MeasureMeasure;

/**
 * Class MeasureMeasureTable
 * @package Monarc\FrontOffice\Model\Table
 */
class MeasureMeasureTable extends AbstractEntityTable
{
    public function __construct(DbCli $dbService, ConnectedUserService $connectedUserService)
    {
        parent::__construct($dbService, MeasureMeasure::class, $connectedUserService);
    }

    public function findByAnrFatherUuidAndChildUuid(Anr $anr, string $fatherUuid, string $childUuid): ?MeasureMeasure
    {
        return $this->getRepository()->createQueryBuilder('mm')
            ->where('mm.anr = :anr')
            ->andWhere('mm.father = :father')
            ->andWhere('mm.child = :child')
            ->setParameter('anr', $anr)
            ->setParameter('father', $fatherUuid)
            ->setParameter('child', $childUuid)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function saveEntity(MeasureMeasure $measureMeasure, bool $flush = true): void
    {
        $em = $this->getDb()->getEntityManager();
        $em->persist($measureMeasure);
        if ($flush) {
            $em->flush();
        }
    }
}
