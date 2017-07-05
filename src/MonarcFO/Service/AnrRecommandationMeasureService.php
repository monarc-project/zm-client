<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) Cases is a registered trademark of SECURITYMADEIN.LU
 * @license   MyCases is licensed under the GNU Affero GPL v3 - See license.txt for more information
 */

namespace MonarcFO\Service;

use MonarcCore\Service\AbstractService;

/**
 * This class is the service that handles measures' recommendations within an ANR. This is a simple CRUD service.
 * @package MonarcFO\Service
 */
class AnrRecommandationMeasureService extends AbstractService
{
    protected $dependencies = ['anr', 'recommandation', 'measure'];
    protected $anrTable;
    protected $userAnrTable;
    protected $recommandationTable;
    protected $measureTable;
}