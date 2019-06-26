<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Service;

use MonarcCore\Service\AbstractService;

/**
 * AnrRecord Actor Service
 *
 * Class AnrRecordActorService
 * @package MonarcFO\Service
 */
class AnrRecordActorService extends AbstractService
{
    protected $dependencies = ['anr'];
    protected $filterColumns = ['label'];
    protected $userAnrTable;
    protected $recordTable;
    protected $processorTable;

    public function orphanActor($actorId, $anrId) {
        $records = $this->recordTable->getEntityByFields(['controller' => $actorId, 'anr' => $anrId]);
        if(count($records)> 0) {
            return false;
        }
        $records = $this->recordTable->getEntityByFields(['dpo' => $actorId, 'anr' => $anrId]);
        if(count($records)> 0) {
            return false;
        }
        $records = $this->recordTable->getEntityByFields(['representative' => $actorId, 'anr' => $anrId]);
        if(count($records)> 0) {
            return false;
        }
        $records = $this->recordTable->getEntityByFields(['jointControllers' => $actorId, 'anr' => $anrId]);
        if(count($records)> 0) {
            return false;
        }
        $processors = $this->processorTable->getEntityByFields(['representative' => $actorId, 'anr' => $anrId]);
        if(count($processors)> 0) {
            return false;
        }
        $processors = $this->processorTable->getEntityByFields(['dpo' => $actorId, 'anr' => $anrId]);
        if(count($processors)> 0) {
            return false;
        }
        $processors = $this->processorTable->getEntityByFields(['cascadedProcessors' => $actorId, 'anr' => $anrId]);
        if(count($processors)> 0) {
            return false;
        }
        return true;
    }

}
