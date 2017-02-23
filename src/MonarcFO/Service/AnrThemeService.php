<?php
/**
 * @link      https://github.com/CASES-LU for the canonical source repository
 * @copyright Copyright (c) Cases is a registered trademark of SECURITYMADEIN.LU
 * @license   MyCases is licensed under the GNU Affero GPL v3 - See license.txt for more information
 */

namespace MonarcFO\Service;

/**
 * This class is the service that handles themes within an ANR. This is a simple CRUD service.
 * @package MonarcFO\Service
 */
class AnrThemeService extends \MonarcCore\Service\AbstractService
{
    protected $filterColumns = ['label1', 'label2', 'label3', 'label4'];
    protected $dependencies = ['anr'];
    protected $anrTable;
    protected $userAnrTable;
}
