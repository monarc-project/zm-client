<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Service;

use MonarcCore\Service\AbstractService;

/**
 * AnrRecord Controller Service
 *
 * Class AnrRecordControllerService
 * @package MonarcFO\Service
 */
class AnrRecordControllerService extends AbstractService
{
    protected $dependencies = ['anr'];
    protected $filterColumns = ['label'];
    protected $userAnrTable;
    protected $recordTable;
    protected $processorTable;

    public function controllerWithoutRecord($controllerId, $recordId, $anrId) {
        $records = $this->recordTable->getEntityByFields(['controller' => $controllerId, 'anr' => $anrId]);
        if(count($records)> 0) {
            return false;
        }
        $jointForRecords = $this->recordTable->getEntityByFields(['jointControllers' => $controllerId, 'anr' => $anrId]);
        if(count($jointForRecords)> 0) {
            return false;
        }
        $behalfForProcessors = $this->processorTable->getEntityByFields(['controllers' => $controllerId, 'anr' => $anrId]);
        if(count($behalfForProcessors)> 0) {
            return false;
        }
        return true;
    }

}
