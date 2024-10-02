<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2023 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Table;

use Doctrine\ORM\EntityManager;
use Monarc\FrontOffice\Entity\AnrInstanceMetadataField;
use Monarc\FrontOffice\Entity\InstanceMetadata;
use Monarc\Core\Table\AbstractTable;
use Monarc\FrontOffice\Entity\Instance;

class InstanceMetadataTable extends AbstractTable
{
    public function __construct(EntityManager $entityManager, string $entityName = InstanceMetadata::class)
    {
        parent::__construct($entityManager, $entityName);
    }

    public function findByInstanceAndMetadataField(
        Instance $instance,
        AnrInstanceMetadataField $anrInstanceMetadataField
    ): ?InstanceMetadata {
        return $this->getRepository()->createQueryBuilder('im')
            ->where('im.instance = :instance')
            ->andWhere('im.anrInstanceMetadataField = :anrInstanceMetadataField')
            ->setParameter('instance', $instance)
            ->setParameter('anrInstanceMetadataField', $anrInstanceMetadataField)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
