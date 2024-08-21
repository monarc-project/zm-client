<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Table;

use Doctrine\ORM\EntityManager;
use Monarc\Core\Table\AbstractTable;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Entity\Measure;

class MeasureTable extends AbstractTable
{
    public function __construct(EntityManager $entityManager, string $entityName = Measure::class)
    {
        parent::__construct($entityManager, $entityName);
    }

    /**
     * @return Measure[]
     */
    public function findByAnrAndReferentialUuidOrderByCode(Anr $anr, string $referentialUuid): array
    {
        return $this->getRepository()->createQueryBuilder('m')
            ->innerJoin('m.referential', 'r')
            ->where('m.anr = :anr')
            ->andWhere('r.uuid = :referentialUuid')
            ->andWhere('r.anr = :anr')
            ->setParameter('referentialUuid', $referentialUuid)
            ->setParameter('anr', $anr)
            ->orderBy('m.code')
            ->getQuery()
            ->getResult();
    }
}
