<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Exception\Exception;
use Monarc\Core\Service\AbstractService;
use Monarc\FrontOffice\Model\Entity\Anr;
use Monarc\FrontOffice\Model\Entity\MonarcObject;
use Monarc\FrontOffice\Model\Table\AnrTable;
use Monarc\FrontOffice\Model\Table\SnapshotTable;
use Monarc\Core\Model\Entity\User;
use Monarc\FrontOffice\Model\Table\UserAnrTable;

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
     * @inheritdoc
     */
    public function create($data, $last = true)
    {
        // duplicate anr and create snapshot record with new id
        /** @var AnrService $anrService */
        $anrService = $this->get('anrService');
        $newAnr = $anrService->duplicateAnr($data['anr'], MonarcObject::SOURCE_CLIENT, null, [], true);

        $data['anrReference'] = $data['anr'];
        $data['anr'] = $newAnr->getId();
        $data['creator'] = $newAnr->getCreator();

        return parent::create($data);
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
    public function deleteFromAnr($id, $anrId = null)
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
     * Restores a snapshot into a separate regular ANR
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

        $anrSnapshot = current($snapshotTable->getEntityByFields(['anrReference' => $anrId, 'id' => $id]));

        // duplicate the anr linked to this snapshot
        $newAnr = $anrService->duplicateAnr($anrSnapshot->get('anr')->get('id'), MonarcObject::SOURCE_CLIENT, null, [], false, true);

        $anrSnapshots = $snapshotTable->getEntityByFields(['anrReference' => $anrId]);
        $i = 1;
        foreach ($anrSnapshots as $s) {
            //define new reference for all snapshots
            $s->set('anrReference', $newAnr->getId());
            $this->setDependencies($s, $this->dependencies);
            $snapshotTable->save($s, count($anrSnapshots) >= $i);
            $i++;
        }

        // resume access
        $userAnrCliTable = $anrService->get('userAnrCliTable');
        $userAnr = $userAnrCliTable->getEntityByFields(['anr' => $anrId]);
        $i = 1;
        foreach ($userAnr as $u) {
            $u->set('anr', $newAnr->getId());
            $this->setDependencies($u, ['anr', 'user']);
            $userAnrCliTable->save($u, count($userAnr) >= $i);
            $i++;
        }

        // delete old anr
        $anrTable->delete($anrId);

        //remove the snap suffix
        for ($i = 1; $i <= 4; ++$i) {
            $newAnr->set('label' . $i, substr($newAnr->get('label' . $i), 7));
        }
        $anrTable->save($newAnr);

        return $newAnr->getId();
    }
}
