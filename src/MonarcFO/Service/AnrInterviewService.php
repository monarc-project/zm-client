<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2018 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Service;

use MonarcCore\Service\AbstractService;

/**
 * This class is the service that handles interviews made on an ANR. This is a basic CRUD service.
 * @package MonarcCore\Service
 */
class AnrInterviewService extends AbstractService
{
    protected $anrTable;
    protected $userAnrTable;
    protected $dependencies = ['anr'];
}
