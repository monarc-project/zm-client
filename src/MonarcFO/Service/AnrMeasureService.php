<?php
/**
 * @link      https://github.com/CASES-LU for the canonical source repository
 * @copyright Copyright (c) Cases is a registered trademark of SECURITYMADEIN.LU
 * @license   MyCases is licensed under the GNU Affero GPL v3 - See license.txt for more information
 */

namespace MonarcFO\Service;

/**
 * Anr Measure Service
 *
 * Class AnrMeasureService
 * @package MonarcFO\Service
 */
class AnrMeasureService extends \MonarcCore\Service\MeasureService
{
    protected $anrTable;
    protected $userAnrTable;
    protected $dependencies = ['anr'];
    protected $forbiddenFields = [];
}
