<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) Cases is a registered trademark of SECURITYMADEIN.LU
 * @license   MyCases is licensed under the GNU Affero GPL v3 - See license.txt for more information
 */

namespace MonarcFO\Service;

use MonarcFO\Service\AbstractService;

/**
 * This class is the service that handles the recommendation events history. This is a simple CRUD service.
 * @package MonarcFO\Service
 */
class AnrRecommandationHistoricService extends \MonarcCore\Service\AbstractService
{
    protected $dependencies = ['anr'];
    protected $userAnrTable;
}