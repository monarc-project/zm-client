<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2021 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

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
}
