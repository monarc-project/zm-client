<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Model\Table;

use Doctrine\ORM\EntityManager;
use Monarc\Core\Model\Table\InstanceRiskOwnerTable as CoreInstanceRiskOwnerTable;
use Monarc\FrontOffice\Model\Entity\InstanceRiskOwner;

class InstanceRiskOwnerTable extends CoreInstanceRiskOwnerTable
{
    public function __construct(EntityManager $entityManager, string $entityName = InstanceRiskOwner::class)
    {
        parent::__construct($entityManager, $entityName);
    }

    public function getEntityClass(): string
    {
        return InstanceRiskOwner::class;
    }
}
