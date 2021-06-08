<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Model\Table;

use Doctrine\ORM\EntityManager;
use Monarc\Core\Model\Entity\OperationalInstanceRiskScale;
use Monarc\Core\Model\Table\AbstractTable;

class OperationalInstanceRiskScaleTable extends AbstractTable
{
    public function __construct(EntityManager $entityManager)
    {
        parent::__construct($entityManager, OperationalInstanceRiskScale::class);
    }
}
