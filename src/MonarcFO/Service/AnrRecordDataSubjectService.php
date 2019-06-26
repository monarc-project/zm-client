<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Service;

use MonarcCore\Service\AbstractService;

/**
 * AnrRecord Data Subject Service
 *
 * Class AnrRecordDataSubjectService
 * @package MonarcFO\Service
 */
class AnrRecordDataSubjectService extends AbstractService
{
    protected $dependencies = ['anr'];
    protected $filterColumns = ['label'];
    protected $userAnrTable;
    protected $personalDataTable;

    public function orphanDataSubject($dataSubjectId, $anrId) {
        $personalData = $this->personalDataTable->getEntityByFields(['dataSubjects' => $dataSubjectId, 'anr' => $anrId]);
        if(count($personalData)> 0) {
            return false;
        }
        return true;
    }
}
