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
use Monarc\Core\Table\Interfaces\PositionUpdatableTableInterface;
use Monarc\Core\Table\Traits\PositionIncrementTableTrait;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Entity\InterestedParty;

class InterestedPartyTable extends AbstractTable implements PositionUpdatableTableInterface
{
    use PositionIncrementTableTrait;

    public function __construct(EntityManager $entityManager, string $entityName = InterestedParty::class)
    {
        parent::__construct($entityManager, $entityName);
    }

    /**
     * @return InterestedParty[]
     */
    public function findByAnrOrderedByPosition(Anr $anr): array
    {
        return $this->getRepository()->createQueryBuilder('i')
            ->where('i.anr = :anr')
            ->setParameter('anr', $anr)
            ->orderBy('i.position', Criteria::ASC)
            ->addOrderBy('i.id', Criteria::ASC)
            ->getQuery()
            ->getResult();
    }
}
