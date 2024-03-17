<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Table;

use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityManager;
use Monarc\Core\Table\InstanceConsequenceTable as CoreInstanceConsequenceTable;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Entity\InstanceConsequence;

class InstanceConsequenceTable extends CoreInstanceConsequenceTable
{
    public function __construct(EntityManager $entityManager, string $entityName = InstanceConsequence::class)
    {
        parent::__construct($entityManager, $entityName);
    }

    public function isEvaluationStarted(Anr $anr): bool
    {
        $queryBuilder = $this->getRepository()->createQueryBuilder('ic');

        return $queryBuilder
            ->innerJoin('ic.instance', 'i')
            ->where('ic.anr = :anr')
            ->setParameter(':anr', $anr)
            ->andWhere($queryBuilder->expr()->orX(
                $queryBuilder->expr()->neq('ic.c', -1),
                $queryBuilder->expr()->neq('ic.i', -1),
                $queryBuilder->expr()->neq('ic.d', -1),
                $queryBuilder->expr()->neq('i.c', -1),
                $queryBuilder->expr()->neq('i.i', -1),
                $queryBuilder->expr()->neq('i.d', -1)
            ))
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult(AbstractQuery::HYDRATE_SIMPLEOBJECT) !== null;
    }
}
