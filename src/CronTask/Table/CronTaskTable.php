<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2022 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\CronTask\Table;

use DateTime;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManager;
use Monarc\Core\Model\Table\AbstractTable;
use Monarc\FrontOffice\Model\Entity\CronTask;

class CronTaskTable extends AbstractTable
{
    public function __construct(EntityManager $entityManager, $entityName = CronTask::class)
    {
        parent::__construct($entityManager, $entityName);
    }

    public function findNewOneByNameWithHigherPriority(string $name): ?CronTask
    {
        return $this->getRepository()->createQueryBuilder('ct')
            ->where('ct.name = :name')
            ->andWhere('ct.status = ' . CronTask::STATUS_NEW)
            ->setParameter('name', $name)
            ->orderBy('ct.priority', Criteria::DESC)
            ->addOrderBy('ct.id', Criteria::ASC)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return CronTask[]
     */
    public function findByNameOrderedByExecutionOrderLimitedByDate(string $name, DateTime $dateTimeFrom): array
    {
        return $this->getRepository()->createQueryBuilder('ct')
            ->where('ct.name = :name')
            ->andWhere('ct.createdAt >= :dateTimeFrom')
            ->setParameter('name', $name)
            ->setParameter('dateTimeFrom', $dateTimeFrom)
            ->orderBy('ct.priority', Criteria::DESC)
            ->addOrderBy('ct.id', Criteria::DESC)
            ->getQuery()
            ->getResult();
    }
}
