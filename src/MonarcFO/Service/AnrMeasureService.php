<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) Cases is a registered trademark of SECURITYMADEIN.LU
 * @license   MyCases is licensed under the GNU Affero GPL v3 - See license.txt for more information
 */

namespace MonarcFO\Service;

/**
 * This class is the service that handles measures in use within an ANR. Inherits its behavior from its MonarcCore
 * parent class MeasureService
 * @see \MonarcCore\Service\MeasureService
 * @package MonarcFO\Service
 */
class AnrMeasureService extends \MonarcCore\Service\MeasureService
{
    protected $anrTable;
    protected $userAnrTable;
    protected $dependencies = ['anr'];
    protected $forbiddenFields = [];
}
