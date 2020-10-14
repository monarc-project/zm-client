<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Model\Table;

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
     * TODO: move it to an abstract table class (also rename the method to save) when we remove AbstractEntityTable.
     * @throws ORMException
     */
    public function saveEntity(Snapshot $snapshot): void
    {
        // TODO: EntityManager has to be injected instead of the db class, db class will be removed at all.
        $this->db->getEntityManager()->persist($snapshot);
        $this->db->getEntityManager()->flush();
    }

    /**
     * @return Snapshot[]|array
     */
    public function findAll(): array
    {
        return $this->getRepository()->findAll();
    }
}
