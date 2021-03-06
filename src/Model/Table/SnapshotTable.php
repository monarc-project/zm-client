<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Model\Table;

use Doctrine\ORM\EntityNotFoundException;
use Doctrine\ORM\ORMException;
use Monarc\Core\Model\Table\AbstractEntityTable;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Model\DbCli;
use Monarc\FrontOffice\Model\Entity\Snapshot;

/**
 * Class SnapshotTable
 * @package Monarc\FrontOffice\Model\Table
 */
class SnapshotTable extends AbstractEntityTable
{
    public function __construct(DbCli $dbService, ConnectedUserService $connectedUserService)
    {
        parent::__construct($dbService, Snapshot::class, $connectedUserService);
    }

    /**
     * @throws ORMException
     */
    public function saveEntity(Snapshot $snapshot, bool $flushAll = true): void
    {
        $em = $this->getDb()->getEntityManager();
        $em->persist($snapshot);
        if ($flushAll) {
            $em->flush();
        }
    }

    /**
     * @throws EntityNotFoundException
     */
    public function findById(int $id): Snapshot
    {
        /** @var Snapshot|null $snapshot */
        $snapshot = $this->getRepository()->find($id);
        if ($snapshot === null) {
            throw new EntityNotFoundException(sprintf('Snapshot with id "%d" was not found', $id));
        }

        return $snapshot;
    }

    /**
     * @return Snapshot[]|array
     */
    public function findAll(): array
    {
        return $this->getRepository()->findAll();
    }
}
