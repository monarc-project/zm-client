<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2023 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Table;

use Doctrine\Common\Collections\Criteria;
use Monarc\Core\Table\AbstractTable;
use Doctrine\ORM\EntityManager;
use Monarc\FrontOffice\Model\Entity\Anr;
use Monarc\FrontOffice\Model\Entity\InstanceRiskOwner;

class InstanceRiskOwnerTable extends AbstractTable
{
    public function __construct(EntityManager $entityManager, string $entityName = InstanceRiskOwner::class)
    {
        parent::__construct($entityManager, $entityName);
    }

    public function findByAnrAndName(Anr $anr, string $name): ?InstanceRiskOwner
    {
        return $this->getRepository()->createQueryBuilder('iro')
            ->where('iro.anr = :anr')
            ->andWhere('iro.name = :name')
            ->setParameter('anr', $anr)
            ->setParameter('name', $name)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return InstanceRiskOwner[]
     */
    public function findByAnrAndFilterParams(Anr $anr, array $params): array
    {
        $queryBuilder = $this->getRepository()->createQueryBuilder('iro')
            ->where('iro.anr = :anr')
            ->orderBy('iro.name', Criteria::ASC)
            ->setParameter('anr', $anr);

        if (!empty($params['name'])) {
            $queryBuilder->andWhere('iro.name LIKE :name')->setParameter('name', '%' . $params['name'] . '%');
        }

        return $queryBuilder->getQuery()->getResult();
    }
}
