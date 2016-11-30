<?php
namespace MonarcFO\Service;

use MonarcFO\Model\Table\AnrTable;
use MonarcFO\Service\AbstractService;

/**
 * Snapshot Service
 *
 * Class SnapshotService
 * @package MonarcFO\Service
 */
class SnapshotService extends \MonarcCore\Service\AbstractService
{
    protected $dependencies = ['anr'];
    protected $anrTable;
    protected $anrService;

    public function create($data, $last = true) {

        $anrReferenceId = $data['anr'];
        unset($data['anr']);

        /** @var AnrService $anrService */
        $anrService = $this->get('anrService');
        $anrId = $anrService->duplicateAnr(intval($anrReferenceId));

        /** @var AnrTable $anrTable */
        $anrTable = $this->get('anrTable');
        $anrReference = $anrTable->getEntity($anrReferenceId);

        $data['anrReference'] = $anrReference;
        $data['anr'] = $anrId;

        return parent::create($data);
    }
}