<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2023 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Doctrine\Common\Collections\Criteria;
use Monarc\Core\Exception\Exception;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Entity\Snapshot;
use Monarc\FrontOffice\Table\AnrTable;
use Monarc\FrontOffice\Table\SnapshotTable;
use Monarc\FrontOffice\Table\UserAnrTable;

class SnapshotService
{
    private AnrService $anrService;

    private AnrTable $anrTable;

    private SnapshotTable $snapshotTable;

    private UserAnrTable $userAnrTable;

    public function __construct(
        SnapshotTable $snapshotTable,
        AnrTable $anrTable,
        AnrService $anrService,
        UserAnrTable $userAnrTable
    ) {
        $this->snapshotTable = $snapshotTable;
        $this->anrTable = $anrTable;
        $this->anrService = $anrService;
        $this->userAnrTable = $userAnrTable;
    }

    public function getList(Anr $anr): array
    {
        $snapshotsList = [];
        $snapshots = $this->snapshotTable->findByAnrAndOrderBy($anr, ['createdAt' => Criteria::DESC]);
        foreach ($snapshots as $snapshot) {
            $snapshotsList[] = [
                'id' => $snapshot->getId(),
                'anr' => [
                    'id' => $snapshot->getAnr()->getId(),
                ],
                'comment' => $snapshot->getComment(),
                'createdAt' => [
                    'date' => $snapshot->getCreatedAt()->format('Y-m-d H:i:s'),
                ],
                'author' => $snapshot->getCreator(),
            ];
        }

        return $snapshotsList;
    }

    public function create(Anr $anr, array $data): Snapshot
    {
        $newAnr = $this->anrService->duplicateAnr($anr, [], 'create');
        /*
         * Snapshots should not be visible on global dashboard
         * and stats not send to the StatsService (but snapshots are ignored anyway).
         */
        $newAnr->setIsVisibleOnDashboard(0)->setIsStatsCollected(0);
        $this->anrTable->save($newAnr, false);

        $snapshot = (new Snapshot())
            ->setAnr($newAnr)
            ->setAnrReference($anr)
            ->setCreator($newAnr->getCreator())
            ->setComment($data['comment']);

        $this->snapshotTable->save($snapshot);

        return $snapshot;
    }

    public function delete(Anr $anr, int $id): void
    {
        /** @var Snapshot $snapshot */
        $snapshot = $this->snapshotTable->findByIdAndAnr($id, $anr);

        $this->snapshotTable->remove($snapshot);
    }

    public function restore(Anr $anrReference, int $snapshotId): Anr
    {
        /** @var Snapshot $snapshot */
        $snapshot = $this->snapshotTable->findById($snapshotId);
        if ($snapshot->getAnrReference()->getId() !== $anrReference->getId()) {
            throw new Exception('The analysis associated with the snapshot does match the requested one.', 412);
        }

        /* Duplicate the anr linked to this snapshot */
        $newAnr = $this->anrService->duplicateAnr($snapshot->getAnr(), [], 'restore');

        /* Update the reference for all the other snapshots. */
        foreach ($anrReference->getReferencedSnapshots() as $referencedSnapshot) {
            $referencedSnapshot->setAnrReference($newAnr);
            $this->snapshotTable->save($referencedSnapshot, false);
        }

        /* Move permissions from the old anr to the new one. */
        foreach ($anrReference->getUsersAnrsPermissions() as $userAnrPermission) {
            $this->userAnrTable->save($userAnrPermission->setAnr($newAnr), false);
        }

        /*
         * We need to set visibility on global dashboard, set stats sending option,
         * swap the uuid of the old anr, that we are going to drop and restore labels.
         */
        $newAnr->setIsVisibleOnDashboard((int)$anrReference->isVisibleOnDashboard())
            ->setIsStatsCollected((int)$anrReference->isStatsCollected())
            ->setLabel($anrReference->getLabel());
        $referenceAnrUuid = $anrReference->getUuid();

        $this->anrTable->remove($anrReference, false);

        $this->anrTable->save($newAnr->setUuid($referenceAnrUuid));

        return $newAnr;
    }
}
