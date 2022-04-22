<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2022 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Model\Table;

use Doctrine\ORM\EntityManager;
use Monarc\FrontOffice\Model\Entity\InstancesMetadatas;
use Monarc\Core\Model\Table\AbstractTable;

class InstancesMetadatasTable extends AbstractTable
{
    public function __construct(EntityManager $entityManager, string $entityName = InstancesMetadatas::class)
    {
        parent::__construct($entityManager, $entityName);
    }
}
