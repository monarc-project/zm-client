<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2023 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Exception\Exception;
use Monarc\FrontOffice\Model\Entity\Anr;
use Monarc\FrontOffice\Model\Entity\Snapshot;
use Monarc\FrontOffice\Table\AnrTable;
use Monarc\FrontOffice\Model\Table\SnapshotTable;
use Monarc\Core\Model\Entity\User;
use Monarc\FrontOffice\Table\UserAnrTable;

// TODO: ...
class SnapshotService
{
    protected $dependencies = ['anr', 'anrReference'];
    protected $filterColumns = [];

    private AnrService $anrService;

    private AnrTable $anrTable;

    private SnapshotTable $snapshotTable;

    public function __construct(SnapshotTable $snapshotTable, AnrTable $anrTable, AnrService $anrService)
    {
        $this->snapshotTable = $snapshotTable;
        $this->anrTable = $anrTable;
        $this->anrService = $anrService;
    }

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

    // TODO: started from this method.
    public function create(Anr $anr, array $data): Snapshot
    {
        $newAnr = $this->anrService->duplicateAnr($anr, [], 'create');

        $snapshot = (new Snapshot())
            ->setAnr($newAnr)
            ->setAnrReference($anr)
            ->setCreator($newAnr->getCreator())
            ->setComment($data['comment']);

        $this->snapshotTable->saveEntity($snapshot);

        /*
         * Snapshots should not be visible on global dashboard
         * and stats not send to the StatsService (but snapshots are ignored anyway).
         */
        $newAnr->setIsVisibleOnDashboard(0)
            ->setIsStatsCollected(0);

        $this->anrTable->save($newAnr);

        return $snapshot;
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

    public function delete(Anr $anr, int $id): void
    {
        $snapshot = $this->snapshotTable->findByIdAndAnr($id, $anr);

        $this->anrService->delete($snapshot->getAnr());
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
        $newAnr = $anrService->duplicateAnr($snapshot->getAnr(), [], 'restore');

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
            ->setLabels($anrReference->getLabels());
        $referenceAnrUuid = $anrReference->getUuid();

        $anrTable->remove($anrReference);

        $anrTable->save($newAnr->setUuid($referenceAnrUuid));

        return $newAnr->getId();
    }
}
