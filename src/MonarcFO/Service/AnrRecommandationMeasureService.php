<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
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