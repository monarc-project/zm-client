<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Model\Table;

use Monarc\Core\Model\Entity\ScaleImpactTypeSuperClass;
use Monarc\Core\Model\Table\AbstractEntityTable;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Model\DbCli;
use Monarc\FrontOffice\Model\Entity\Anr;
use Monarc\FrontOffice\Model\Entity\Scale;
use Monarc\FrontOffice\Model\Entity\ScaleImpactType;

/**
 * Class ScaleImpactTypeTable
 * @package Monarc\FrontOffice\Model\Table
 */
class ScaleImpactTypeTable extends AbstractEntityTable
{
    public function __construct(DbCli $dbService, ConnectedUserService $connectedUserService)
    {
        parent::__construct($dbService, ScaleImpactType::class, $connectedUserService);
    }

    /**
     * @return ScaleImpactType[]
     */
    public function findByAnr(Anr $anr): array
    {
        return $this->getRepository()
            ->createQueryBuilder('sit')
            ->where('sit.anr = :anr')
            ->setParameter('anr', $anr)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return ScaleImpactType[]
     */
    public function findByAnrOrderedByPosition(Anr $anr): array
    {
        return $this->getRepository()
            ->createQueryBuilder('sit')
            ->where('sit.anr = :anr')
            ->setParameter('anr', $anr)
            ->addOrderBy('sit.position')
            ->getQuery()
            ->getResult();
    }

    public function findMaxPositionByAnrAndScale(Anr $anr, Scale $scale): int
    {
        return (int)$this->getRepository()
            ->createQueryBuilder('sit')
            ->select('MAX(sit.position)')
            ->where('sit.anr = :anr')
            ->andWhere('sit.scale = :scale')
            ->setParameter('anr', $anr)
            ->setParameter('scale', $scale)
            ->setMaxResults(1)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function saveEntity(ScaleImpactTypeSuperClass $scaleImpactType, bool $flushAll = true): void
    {
        $em = $this->getDb()->getEntityManager();
        $em->persist($scaleImpactType);
        if ($flushAll) {
            $em->flush();
        }
    }
}
