<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Model\Table;

use Monarc\Core\Model\Table\AbstractEntityTable;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Model\DbCli;
use Monarc\FrontOffice\Model\Entity\Anr;
use Monarc\FrontOffice\Model\Entity\Measure;
use Monarc\FrontOffice\Model\Entity\Soa;
use Monarc\FrontOffice\Model\Entity\SoaCategory;

/**
 * Class SoaTable
 * @package Monarc\FrontOffice\Model\Table
 */
class SoaTable extends AbstractEntityTable
{
    public function __construct(DbCli $dbService, ConnectedUserService $connectedUserService)
    {
        parent::__construct($dbService, Soa::class, $connectedUserService);
    }

    /**
     * @param Anr $anr
     *
     * @return Soa[]
     */
    public function findByAnr(Anr $anr): array
    {
        return $this->getRepository()
            ->createQueryBuilder('s')
            ->where('s.anr = :anr')
            ->setParameter('anr', $anr)
            ->getQuery()
            ->getResult();
    }

    public function saveEntity(Soa $soa, bool $flushAll = true): void
    {
        $em = $this->getDb()->getEntityManager();
        $em->persist($soa);
        if ($flushAll) {
            $em->flush();
        }
    }

    /**
     * @return Soa[]
     */
    public function findByAnrAndSoaCategory(Anr $anr, SoaCategory $soaCategory, array $order = [])
    {
        $queryBuilder = $this->getRepository()
            ->createQueryBuilder('s')
            ->innerJoin('s.measure', 'm')
            ->where('s.anr = :anr')
            ->andWhere('m.category = :category')
            ->setParameter('anr', $anr)
            ->setParameter('category', $soaCategory);

        if (!empty($order)) {
            foreach ($order as $filed => $direction) {
                $queryBuilder->addOrderBy($filed, $direction);
            }
        }

        return $queryBuilder->getQuery()->getResult();
    }


    public function findByMeasure(Measure $measure): ?Soa
    {
        return $this->getRepository()
            ->createQueryBuilder('s')
            ->innerJoin('s.measure', 'm')
            ->where('m.uuid = :measure_uuid')
            ->andWhere('m.anr = :measure_anr')
            ->setParameter('measure_uuid', $measure->getUuid())
            ->setParameter('measure_anr', $measure->getAnr())
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findById(int $id): ?Soa
    {
        return $this->getRepository()
            ->createQueryBuilder('s')
            ->where('s.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
