<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2022 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Table;

use Doctrine\ORM\EntityManager;
use Monarc\Core\Table\AbstractTable;
use Monarc\FrontOffice\Model\Entity\Anr;
use Monarc\FrontOffice\Model\Entity\Asset;

class AssetTable extends AbstractTable
{
    public function __construct(EntityManager $entityManager, string $entityName = Asset::class)
    {
        parent::__construct($entityManager, $entityName);
    }

    public function findByAnrAndUuid(Anr $anr, string $uuid): ?Asset
    {
        return $this->getRepository()
            ->createQueryBuilder('a')
            ->where('a.anr = :anr')
            ->andWhere('a.uuid = :uuid')
            ->setParameter('anr', $anr)
            ->setParameter('uuid', $uuid)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByAnrAndCode(Anr $anr, string $code): ?Asset
    {
        return $this->getRepository()->createQueryBuilder('a')
            ->where('a.anr = :anr')
            ->andWhere('a.code = :code')
            ->setParameter('anr', $anr)
            ->setParameter('code', $code)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
