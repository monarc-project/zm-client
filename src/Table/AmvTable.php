<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2023 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Table;

use Doctrine\ORM\EntityManager;
use Monarc\Core\Table\AbstractTable;
use Monarc\Core\Table\Interfaces\PositionUpdatableTableInterface;
use Monarc\Core\Table\Traits\PositionIncrementTableTrait;
use Monarc\FrontOffice\Entity\Amv;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Entity\Asset;

class AmvTable extends AbstractTable implements PositionUpdatableTableInterface
{
    use PositionIncrementTableTrait;

    public function __construct(EntityManager $entityManager, string $entityName = Amv::class)
    {
        parent::__construct($entityManager, $entityName);
    }

    public function findByAssetAndPosition(Asset $asset, int $position): ?Amv
    {
        return $this->getRepository()->createQueryBuilder('amv')
            ->innerJoin('amv.asset', 'a')
            ->where('amv.anr = :anr')
            ->andWhere('a.uuid = :asset_uuid')
            ->andWhere('a.anr = :anr')
            ->andWhere('amv.position = :position')
            ->setParameter('anr', $asset->getAnr())
            ->setParameter('asset_uuid', $asset->getUuid())
            ->setParameter('position', $position)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByAmvItemsUuidsAndAnr(
        string $assetUuid,
        string $threatUuid,
        string $vulnerabilityUuid,
        Anr $anr
    ): ?Amv {
        return $this->getRepository()
            ->createQueryBuilder('amv')
            ->innerJoin('amv.asset', 'a')
            ->innerJoin('amv.threat', 't')
            ->innerJoin('amv.vulnerability', 'v')
            ->andWhere('amv.anr = :anr')
            ->andWhere('a.uuid = :assetUuid')
            ->andWhere('a.anr = :anr')
            ->andWhere('t.uuid = :threatUuid')
            ->andWhere('t.anr = :anr')
            ->andWhere('v.uuid = :vulnerabilityUuid')
            ->andWhere('v.anr = :anr')
            ->setParameter('assetUuid', $assetUuid)
            ->setParameter('threatUuid', $threatUuid)
            ->setParameter('vulnerabilityUuid', $vulnerabilityUuid)
            ->setParameter('anr', $anr)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
