<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2026 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Table;

use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManager;
use Monarc\Core\Table\AbstractTable;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Entity\InstanceRisk;
use Monarc\FrontOffice\Entity\InstanceRiskOp;
use Monarc\FrontOffice\Entity\RiskSource;

class RiskSourceTable extends AbstractTable
{
    public function __construct(EntityManager $entityManager, string $entityName = RiskSource::class)
    {
        parent::__construct($entityManager, $entityName);
    }

    /**
     * @return RiskSource[]
     */
    public function findByFilterParams(Anr $anr, array $params = []): array
    {
        $queryBuilder = $this->getRepository()->createQueryBuilder('rs')
            ->where('rs.anr = :anr')
            ->setParameter('anr', $anr);

        if (array_key_exists('isActive', $params) && $params['isActive'] !== null) {
            $queryBuilder
                ->andWhere('rs.isActive = :isActive')
                ->setParameter('isActive', (bool)$params['isActive']);
        }

        if (!empty($params['label'])) {
            $queryBuilder
                ->andWhere('rs.label LIKE :label')
                ->setParameter('label', '%' . trim((string)$params['label']) . '%');
        }

        return $queryBuilder
            ->orderBy('rs.isDefault', Criteria::DESC)
            ->addOrderBy('rs.label', Criteria::ASC)
            ->getQuery()
            ->getResult();
    }

    public function findOneByAnrAndLabel(Anr $anr, string $label): ?RiskSource
    {
        return $this->getRepository()->createQueryBuilder('rs')
            ->where('rs.anr = :anr')
            ->andWhere('LOWER(rs.label) = :label')
            ->setParameter('anr', $anr)
            ->setParameter('label', mb_strtolower(trim($label)))
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function isUsedInRisks(RiskSource $riskSource): bool
    {
        $isUsedInInformationRisks = (bool)$this->entityManager->createQueryBuilder()
            ->select('COUNT(ir.id)')
            ->from(InstanceRisk::class, 'ir')
            ->where('ir.riskSource = :riskSource')
            ->setParameter('riskSource', $riskSource)
            ->getQuery()
            ->getSingleScalarResult();

        if ($isUsedInInformationRisks) {
            return true;
        }

        return (bool)$this->entityManager->createQueryBuilder()
            ->select('COUNT(iro.id)')
            ->from(InstanceRiskOp::class, 'iro')
            ->where('iro.riskSource = :riskSource')
            ->setParameter('riskSource', $riskSource)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
