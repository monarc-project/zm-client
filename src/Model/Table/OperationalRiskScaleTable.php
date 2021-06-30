<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Model\Table;

use Doctrine\ORM\EntityManager;
use Monarc\Core\Model\Table\OperationalRiskScaleTable as CoreOperationalRiskScaleTable;
use Monarc\FrontOffice\Model\Entity\OperationalRiskScale;

class OperationalRiskScaleTable extends CoreOperationalRiskScaleTable
{
    public function __construct(EntityManager $entityManager, string $entityName = OperationalRiskScale::class)
    {
        parent::__construct($entityManager, $entityName);
    }
}
