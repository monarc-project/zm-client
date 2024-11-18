<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Doctrine\Common\Collections\Criteria;
use Monarc\Core\Entity\UserSuperClass;
use Monarc\Core\Exception\Exception;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Entity\Snapshot;
use Monarc\FrontOffice\Table;

class SnapshotService
{
    private UserSuperClass $connectedUser;

    public function __construct(
        private Table\SnapshotTable $snapshotTable,
        private Table\AnrTable $anrTable,
        private AnrService $anrService,
        private Table\UserAnrTable $userAnrTable,
        private Table\UserTable $userTable,
        ConnectedUserService $connectedUserService
    ) {
        $this->connectedUser = $connectedUserService->getConnectedUser();
    }

    public function getList(Anr $anr): array
    {
        $snapshotsList = [];
        $snapshots = $this->snapshotTable->findByAnrReferenceAndOrderBy($anr, ['createdAt' => Criteria::DESC]);
        foreach ($snapshots as $snapshot) {
            $snapshotsList[] = [
                'id' => $snapshot->getId(),
                'anr' => [
                    'id' => $snapshot->getAnr()->getId(),
                ],
                'comment' => $snapshot->getComment(),
                'createdAt' => [
                    'date' => $snapshot->getCreatedAt()->format('Y-m-d H:i:s.u'),
                ],
                'creator' => $snapshot->getCreator(),
            ];
        }

        return $snapshotsList;
    }

    public function create(Anr $anr, array $data): Snapshot
    {
        $newAnr = $this->anrService->duplicateAnr($anr, [], true);
        /*
         * Snapshots should not be visible on global dashboard
         * and stats not send to the StatsService (but snapshots are ignored anyway).
         */
        $newAnr->setIsVisibleOnDashboard(0)->setIsStatsCollected(0);
        $this->anrTable->save($newAnr, false);

        $snapshot = (new Snapshot())
            ->setAnr($newAnr)
            ->setAnrReference($anr)
            ->setCreator($this->connectedUser->getFirstname() . ' ' . $this->connectedUser->getLastname())
            ->setComment($data['comment']);

        $this->snapshotTable->save($snapshot);

        return $snapshot;
    }

    public function delete(Anr $anr, int $id): void
    {
        /** @var Snapshot $snapshot */
        $snapshot = $this->snapshotTable->findByIdAndAnrReference($id, $anr);

        $this->snapshotTable->remove($snapshot);
    }

    public function restore(Anr $anrReference, int $snapshotId): Anr
    {
        /** @var Snapshot $snapshot */
        $snapshot = $this->snapshotTable->findById($snapshotId);
        if ($snapshot->getAnrReference()->getId() !== $anrReference->getId()) {
            throw new Exception('The analysis associated with the snapshot matches the requested one.', 412);
        }

        /* Use the anr from the snapshot as a new one. */
        $snapshotAnr = $snapshot->getAnr();

        /* Update the reference for all the other snapshots. */
        foreach ($anrReference->getReferencedSnapshots() as $referencedSnapshot) {
            if ($snapshot->getId() !== $referencedSnapshot->getId()) {
                $this->snapshotTable->save($referencedSnapshot->setAnrReference($snapshotAnr), false);
            }
            $anrReference->removeReferencedSnapshot($referencedSnapshot);
        }

        /* Move permissions from the old anr to the new one. */
        foreach ($anrReference->getUsersAnrsPermissions() as $userAnrPermission) {
            $anrReference->removeUserAnrPermission($userAnrPermission);
            $this->userAnrTable->save($userAnrPermission->setAnr($snapshotAnr), false);
        }

        /*
         * We need to set visibility on global dashboard, set stats sending option,
         * swap the uuid of the old anr, that we are going to drop and restore labels.
         */
        $snapshotAnr->setIsVisibleOnDashboard((int)$anrReference->isVisibleOnDashboard())
            ->setIsStatsCollected((int)$anrReference->isStatsCollected())
            ->setLabel($anrReference->getLabel());
        $referenceAnrUuid = $anrReference->getUuid();

        $this->userTable->save($this->connectedUser->setCurrentAnr($snapshotAnr), false);

        $this->snapshotTable->save($snapshot->setAnr($anrReference), false);
        $this->anrTable->save($anrReference->generateAndSetUuid());
        try {
            $this->anrTable->remove($anrReference, false);
            $this->snapshotTable->remove($snapshot, false);
            $this->anrTable->save($snapshotAnr->setUuid($referenceAnrUuid));
        } catch (\Throwable $e) {
            $this->snapshotTable->save($snapshot->setAnr($snapshotAnr), false);
            $this->anrTable->save($anrReference->setUuid($referenceAnrUuid));
            throw $e;
        }

        return $snapshotAnr;
    }
}
