<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Table;

use Doctrine\ORM\EntityManager;
use Monarc\Core\Table\ScaleImpactTypeTable as CoreScaleImpactTypeTable;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Entity\ScaleImpactType;

class ScaleImpactTypeTable extends CoreScaleImpactTypeTable
{
    public function __construct(EntityManager $entityManager, string $entityName = ScaleImpactType::class)
    {
        parent::__construct($entityManager, $entityName);
    }

    // TODO: there is no position field anymore.
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

    /**
     * @return ScaleImpactType[]
     */
    public function findByAnrOrderedAndIndexedByPosition(Anr $anr): array
    {
        return $this->getRepository()
            ->createQueryBuilder('sit', 'sit.position')
            ->where('sit.anr = :anr')
            ->setParameter('anr', $anr)
            ->addOrderBy('sit.position')
            ->getQuery()
            ->getResult();
    }
}
