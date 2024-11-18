<?php declare(strict_types=1);
/**
* @link      https://github.com/monarc-project for the canonical source repository
* @copyright Copyright (c) 2016-2023 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
* @license   MONARC is licensed under GNU Affero General Public License version 3
*/

namespace Monarc\FrontOffice\Table;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityNotFoundException;
use Monarc\Core\Table\AbstractTable;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Entity\Snapshot;

class SnapshotTable extends AbstractTable
{
    public function __construct(EntityManager $entityManager, string $entityName = Snapshot::class)
    {
        parent::__construct($entityManager, $entityName);
    }

    /**
     * @return Snapshot[]
     */
    public function findAll(): array
    {
        return $this->getRepository()->findAll();
    }

    /**
     * @return Snapshot[]
     */
    public function findByAnrReferenceAndOrderBy(Anr $anr, array $order): array
    {
        $queryBuilder = $this->getRepository()->createQueryBuilder('s')
            ->where('s.anrReference = :anr')
            ->setParameter('anr', $anr);

        foreach ($order as $field => $direction) {
            $queryBuilder->addOrderBy('s.' . $field, $direction);
        }

        return $queryBuilder->getQuery()->getResult();
    }

    public function findByIdAndAnrReference(int $id, Anr $anr): Snapshot
    {
        $snapshot = $this->getRepository()->createQueryBuilder('s')
            ->where('s.id = :id')
            ->andWhere('s.anrReference = :anr')
            ->setParameter('id', $id)
            ->setParameter('anr', $anr)
            ->getQuery()
            ->getOneOrNullResult();
        if ($snapshot === null) {
            throw EntityNotFoundException::fromClassNameAndIdentifier(SnapshotTable::class, [$id, $anr->getId()]);
        }

        return $snapshot;
    }
}
