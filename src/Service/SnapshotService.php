<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Doctrine\ORM\ORMException;
use Monarc\Core\Exception\Exception;
use Monarc\Core\Service\AbstractService;
use Monarc\FrontOffice\Model\Entity\Anr;
use Monarc\FrontOffice\Model\Entity\MonarcObject;
use Monarc\FrontOffice\Model\Entity\Snapshot;
use Monarc\FrontOffice\Model\Table\AnrTable;
use Monarc\FrontOffice\Model\Table\SnapshotTable;
use Monarc\Core\Model\Entity\User;
use Monarc\FrontOffice\Table\UserAnrTable;

/**
 * This class is the service that handles snapshots. Snapshots are backups of ANRs at a specific point in time, and
 * may be consulted or restored at any time.
 * @package Monarc\FrontOffice\Service
 */
class SnapshotService extends AbstractService
{
    protected $dependencies = ['anr', 'anrReference'];
    protected $filterColumns = [];
    protected $anrTable;
    /** @var UserAnrTable */
    protected $userAnrTable;
    protected $anrService;

    /**
     * @inheritdoc
     */
    public function getList($page = 1, $limit = 25, $order = null, $filter = null, $filterAnd = null)
    {
        if (is_null($order)) {
            $order = '-id';
        }
        /** @var SnapshotTable $table */
        $table = $this->get('table');
        return $table->fetchAllFiltered(
            array_keys($this->get('entity')->getJsonArray()),
            $page,
            $limit,
            $this->parseFrontendOrder($order),
            $this->parseFrontendFilter($filter, $this->filterColumns),
            $filterAnd
        );
    }

    /**
     * @throws ORMException
     * @throws Exception
     */
    public function create($data, $last = true): int
    {
        // duplicate anr and create snapshot record with new id
        /** @var AnrService $anrService */
        $anrService = $this->get('anrService');
        $newAnr = $anrService->duplicateAnr($data['anr'], MonarcObject::SOURCE_CLIENT, null, [], true);

        /** @var AnrTable $anrTable */
        $anrTable = $this->get('anrTable');
        /** @var SnapshotTable $snapshotTable */
        $snapshotTable = $this->get('table');

        // TODO: Refactor this service and AnrService to be able to pass snapshot data in the constructor.
        $snapshot = (new Snapshot())
            ->setAnr($newAnr)
            ->setAnrReference($data['anr'] instanceof Anr ? $data['anr'] : $anrTable->findById($data['anr']))
            ->setCreator($newAnr->getCreator())
            ->setComment($data['comment']);

        $snapshotTable->saveEntity($snapshot);

        // Snapshots should not be visible on global dashboard
        // and stats not send to the StatsService (but snapshots are ignored anyway).
        $newAnr->setIsVisibleOnDashboard(0)
            ->setIsStatsCollected(0);

        $anrTable->saveEntity($newAnr);

        return $snapshot->getId();
    }

    /**
     * @inheritdoc
     */
    public function patch($id, $data)
    {
        foreach ($data as $key => $value) {
            if ($key !== 'comment') {
                unset($data[$key]);
            }
        }
        return parent::patch($id, $data);
    }

    /**
     * @inheritdoc
     */
    public function update($id, $data)
    {
        return $this->patch($id, $data);
    }

    /**
     * @inheritdoc
     */
    public function delete($id)
    {
        /** @var SnapshotTable $snapshotTable */
        $snapshotTable = $this->get('table');
        $snapshot = $snapshotTable->getEntity($id);

        /** @var AnrService $anrService */
        $anrService = $this->get('anrService');

        return $anrService->delete($snapshot->anr->id);
    }

    /**
     * @inheritdoc
     */
    public function deleteInstanceRisk($id, $anrId = null)
    {
        // Ensure user is allowed to perform this action
        if ($anrId !== null) {
            $entity = $this->get('table')->getEntity($id);
            if ($entity->anrReference->id !== $anrId) {
                throw new Exception('Anr id error', 412);
            }

            /** @var User $connectedUser */
            $connectedUser = $this->get('table')->getConnectedUser();

            /** @var UserAnrTable $userAnrTable */
            $userAnrTable = $this->get('userAnrTable');
            $rights = $userAnrTable->getEntityByFields(['user' => $connectedUser->getId(), 'anr' => $anrId]);
            $rwd = 0;
            foreach ($rights as $right) {
                if ($right->rwd == 1) {
                    $rwd = 1;
                }
            }

            if (!$rwd) {
                throw new Exception('You are not authorized to do this action', 412);
            }
        }

        return $this->delete($id);
    }

    /**
     * Restores a snapshot into a separate regular Anr.
     *
     * @param int $anrId Reference ANR ID
     * @param int $id Snapshot ID to restore
     * @return int Newly created ANR ID
     */
    public function restore($anrId, $id)
    {
        // switch anr and anrReference
        /** @var SnapshotTable $snapshotTable */
        $snapshotTable = $this->get('table');
        /** @var AnrService $anrService */
        $anrService = $this->get('anrService');
        /** @var AnrTable $anrTable */
        $anrTable = $this->get('anrTable');

        $snapshot = $snapshotTable->findById($id);
        /** @var Anr $anrReference */
        $anrReference = $snapshot->getAnrReference();

        // duplicate the anr linked to this snapshot
        $newAnr = $anrService->duplicateAnr($snapshot->getAnr(), MonarcObject::SOURCE_CLIENT, null, [], false, true);

        /** @var Snapshot[] $snapshots */
        $snapshots = $snapshotTable->getEntityByFields(['anrReference' => $anrId]);
        //define new reference for all snapshots
        foreach ($snapshots as $snap) {
            $snap->setAnrReference($newAnr);
            $snapshotTable->saveEntity($snap);
        }

        /**
         * Transmit the to the new anr (restored from snapshot) access and settings from the replaced (old) anr.
         */
        /** @var UserAnrTable $userAnrTable */
        $usersAnrs = $this->userAnrTable->findByAnr($anrReference);

        foreach ($usersAnrs as $userAnr) {
            $userAnr->setAnr($newAnr);
            $userAnrTable->saveEntity($userAnr);
        }

        /*
         * We need to set visibility on global dashboard, set stats sending option,
         * swap the uuid of the old anr, that we are going to drop and restore labels.
         */
        $newAnr->setIsVisibleOnDashboard((int)$anrReference->isVisibleOnDashboard())
            ->setIsStatsCollected((int)$anrReference->isStatsCollected())
            ->setLabels([
                'label1' => $anrReference->getLabelByLanguageIndex(1),
                'label2' => $anrReference->getLabelByLanguageIndex(2),
                'label3' => $anrReference->getLabelByLanguageIndex(3),
                'label4' => $anrReference->getLabelByLanguageIndex(4),
            ]);
        $referenceAnrUuid = $anrReference->getUuid();

        $anrTable->deleteEntity($anrReference);

        $anrTable->saveEntity($newAnr->setUuid($referenceAnrUuid));

        return $newAnr->getId();
    }
}
