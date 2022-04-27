<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2022 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Model\Table;

use Doctrine\ORM\EntityManager;
use Monarc\FrontOffice\Model\Entity\InstanceMetadata;
use Monarc\Core\Model\Table\AbstractTable;
use Monarc\FrontOffice\Model\Entity\Instance;

class InstanceMetadataTable extends AbstractTable
{
    public function __construct(EntityManager $entityManager, string $entityName = InstanceMetadata::class)
    {
        parent::__construct($entityManager, $entityName);
    }

    /**
     * @return InstanceMetadata[]
     */
    public function findByInstance(Instance $instance): array
    {
        return $this->getRepository()->createQueryBuilder('im')
            ->where('im.instance = :instance')
            ->setParameter('instance', $instance)
            ->getQuery()
            ->getResult();
    }
}
