<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Table;

use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityManager;
use Monarc\Core\Table\AbstractTable;
use Monarc\Core\Table\Interfaces\UniqueCodeTableInterface;
use Monarc\Core\Table\Traits\CodeExistenceValidationTableTrait;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Entity\Threat;

class ThreatTable extends AbstractTable implements UniqueCodeTableInterface
{
    use CodeExistenceValidationTableTrait;

    public function __construct(EntityManager $entityManager, string $entityName = Threat::class)
    {
        parent::__construct($entityManager, $entityName);
    }

    public function isEvaluationStarted(Anr $anr): bool
    {
        return $this->getRepository()->createQueryBuilder('t')
            ->where('t.anr = :anr')
            ->setParameter(':anr', $anr)
            ->andWhere('t.qualification != -1')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult(AbstractQuery::HYDRATE_SIMPLEOBJECT) !== null;
    }
}