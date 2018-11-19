<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2018 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Service;

use MonarcCore\Service\AbstractService;

/**
 * AnrMeasureMeasureService Service
 *
 * Class AnrMeasureMeasureService
 * @package MonarcFO\Service
 */
class AnrMeasureMeasureService extends AbstractService
{
    protected $table;
    protected $entity;
    protected $anrTable;
    protected $userAnrTable;
    protected $dependencies = ['category' ,'anr'];
    protected $forbiddenFields = [];
}
