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
    protected $userAnrTable;

}
