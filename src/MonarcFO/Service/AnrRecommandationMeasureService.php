<?php
/**
 * @link      https://github.com/CASES-LU for the canonical source repository
 * @copyright Copyright (c) Cases is a registered trademark of SECURITYMADEIN.LU
 * @license   MyCases is licensed under the GNU Affero GPL v3 - See license.txt for more information
 */

namespace MonarcFO\Service;

use MonarcFO\Service\AbstractService;

/**
 * Anr Recommandation Measure Service
 *
 * Class AnrRecommandationMeasureService
 * @package MonarcFO\Service
 */
class AnrRecommandationMeasureService extends \MonarcCore\Service\AbstractService
{
    protected $dependencies = ['anr', 'recommandation', 'measure'];
    protected $anrTable;
    protected $userAnrTable;
    protected $recommandationTable;
    protected $measureTable;
}