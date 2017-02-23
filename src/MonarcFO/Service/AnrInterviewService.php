<?php
/**
 * @link      https://github.com/CASES-LU for the canonical source repository
 * @copyright Copyright (c) Cases is a registered trademark of SECURITYMADEIN.LU
 * @license   MyCases is licensed under the GNU Affero GPL v3 - See license.txt for more information
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
