<?php
namespace MonarcFO\Service;

use MonarcFO\Model\Entity\Anr;
use MonarcFO\Model\Table\AnrTable;
use MonarcFO\Model\Table\SnapshotTable;
use MonarcFO\Service\AbstractService;

/**
 * Snapshot Service
 *
 * Class SnapshotService
 * @package MonarcFO\Service
 */
class SnapshotService extends \MonarcCore\Service\AbstractService
{
    protected $dependencies = ['anr', 'anrReference'];
    protected $filterColumns = [];
    protected $anrTable;
    protected $anrService;

    /**
     * Get List
     *
     * @param int $page
     * @param int $limit
     * @param null $order
     * @param null $filter
     * @return mixed
     */
    public function getList($page = 1, $limit = 25, $order = null, $filter = null, $filterAnd = null){

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
     * Create
     *
     * @param $data
     * @return mixed
     */
    public function create($data, $last = true) {

        $data['anrReference'] = $data['anr'];
        unset($data['anr']);

        /** @var AnrService $anrService */
        $anrService = $this->get('anrService');
        $anrId = $anrService->duplicateAnr($data['anrReference'], \MonarcFO\Model\Entity\Object::SOURCE_CLIENT, null, [], true);

        /** @var AnrTable $anrTable */
        //$anrTable = $this->get('anrTable');
        //$anrReference = $anrTable->getEntity($anrReferenceId);

        //$data['anrReference'] = $anrReference;
        $data['anr'] = $anrId;

        return parent::create($data);
    }

    /**
     * Patch
     *
     * @param $id
     * @param $data
     * @return mixed
     */
    public function patch($id, $data){

        foreach($data as $key => $value) {
            if ($key != 'comment') {
                unset($data[$key]);
            }
        }

        return parent::patch($id, $data);
    }

    /**
     * Update
     *
     * @param $id
     * @param $data
     * @return mixed
     */
    public function update($id, $data) {
        return $this->patch($id, $data);
    }

    /**
     * Delete
     *
     * @param $id
     */
    public function delete($id) {

        /** @var SnapshotTable $snapshotTable */
        $snapshotTable = $this->get('table');
        $snapshot = $snapshotTable->getEntity($id);

        /** @var AnrService $anrService */
        $anrService = $this->get('anrService');

        return $anrService->delete($snapshot->anr->id);
    }

    /**
     * Restore
     *
     * @param $anrId
     * @return mixed
     */
    public function restore($anrId, $id) {
        //switch anr and anrReference
        /** @var SnapshotTable $snapshotTable */
        $snapshotTable = $this->get('table');
        /** @var AnrService $anrService */
        $anrService = $this->get('anrService');
        /** @var AnrTable $anrTable */
        $anrTable = $this->get('anrTable');

        $anrSnapshot = current($snapshotTable->getEntityByFields(['anrReference' => $anrId, 'id' => $id]));

        $newAnrId = $anrService->duplicateAnr($anrSnapshot->get('anr')->get('id')); // on duplique l'anr liée au snapshot

        $anrSnapshots = $snapshotTable->getEntityByFields(['anrReference' => $anrId]);
        $i = 1;
        foreach($anrSnapshots as $s){
            $s->set('anrReference',$newAnrId); // et on définie la nouvelle référence pour tous les snapshots
            $this->setDependencies($s,$this->dependencies);
            $snapshotTable->save($s,count($anrSnapshots) >= $i);
            $i++;
        }

        $anrTable->delete($anrId); // on supprime l'ancienne anr

        /** @var Anr $newAnrSnapshot */
        $newAnrSnapshot = $anrTable->getEntity($newAnrId);

        // Remove the snap suffix
        for ($i = 1; $i <= 4; ++$i) {
            $newAnrSnapshot->set('label' . $i, substr($newAnrSnapshot->get('label' . $i), 7));
        }
        $anrTable->save($newAnrSnapshot);

        return $newAnrId;
    }
}