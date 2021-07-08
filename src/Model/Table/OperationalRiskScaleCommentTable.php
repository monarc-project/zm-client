<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Model\Table;

use Doctrine\ORM\EntityManager;
use Monarc\Core\Model\Table\OperationalRiskScaleCommentTable as CoreOperationalRiskScaleCommentTable;
use Monarc\FrontOffice\Model\Entity\OperationalRiskScaleComment;

class OperationalRiskScaleCommentTable extends CoreOperationalRiskScaleCommentTable
{
    public function __construct(EntityManager $entityManager, $entityName = OperationalRiskScaleComment::class)
    {
        parent::__construct($entityManager, $entityName);
    }
}
