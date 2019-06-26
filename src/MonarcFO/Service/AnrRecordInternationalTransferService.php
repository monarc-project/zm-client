<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Service;

use MonarcCore\Service\AbstractService;

/**
 * AnrRecord International Transfer Service
 *
 * Class AnrRecordInternationalTransferService
 * @package MonarcFO\Service
 */
class AnrRecordInternationalTransferService extends AbstractService
{
    protected $dependencies = ['anr', 'record', 'processor'];
    protected $filterColumns = ['label'];
    protected $userAnrTable;
    protected $anrTable;

}
