<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Service;

use MonarcCore\Service\AbstractService;

/**
 * AnrRecord Recipient Category Service
 *
 * Class AnrRecordRecipientCategoryService
 * @package MonarcFO\Service
 */
class AnrRecordRecipientCategoryService extends AbstractService
{
    protected $dependencies = ['anr'];
    protected $filterColumns = ['label'];
    protected $userAnrTable;
    protected $recordTable;

    public function recipientCategoryWithoutRecord($recipientId, $recordId, $anrId) {
        $records = $this->recordTable->getEntityByFields(['recipients' => $recipientId, 'anr' => $anrId]);
        if(count($records) > 0) {
            return false;
        }
        return true;
    }
}