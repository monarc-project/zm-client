<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Model\Table;

use Monarc\FrontOffice\Model\DbCli;
use Monarc\Core\Model\Table\AbstractEntityTable;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Model\Entity\Anr;
use Monarc\FrontOffice\Model\Entity\Asset;

/**
 * Class AssetTable
 * @package Monarc\FrontOffice\Model\Table
 */
class AssetTable extends AbstractEntityTable
{
    public function __construct(DbCli $dbService, ConnectedUserService $connectedUserService)
    {
        parent::__construct($dbService, Asset::class, $connectedUserService);
    }

    public function findByAnrAndUuid(Anr $anr, string $uuid): ?Asset
    {
        return $this->getRepository()
            ->createQueryBuilder('a')
            ->where('a.anr = :anr')
            ->andWhere('a.uuid = :uuid')
            ->setParameter('anr', $anr)
            ->setParameter('uuid', $uuid)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function saveEntity(Asset $asset, bool $flushAll = true): void
    {
        $em = $this->getDb()->getEntityManager();
        $em->persist($asset);
        if ($flushAll) {
            $em->flush();
        }
    }
}
