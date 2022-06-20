<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2022 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Model\Table;

use Doctrine\ORM\EntityManager;
use Monarc\Core\Model\Table\AnrMetadatasOnInstancesTable as CoreAnrMetadatasOnInstancesTable;
use Monarc\FrontOffice\Model\Entity\AnrMetadatasOnInstances;

class AnrMetadatasOnInstancesTable extends CoreAnrMetadatasOnInstancesTable
{
    public function __construct(EntityManager $entityManager, string $entityName = AnrMetadatasOnInstances::class)
    {
        parent::__construct($entityManager, $entityName);
    }
}
