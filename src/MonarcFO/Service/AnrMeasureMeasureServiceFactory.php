<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;

/**
 * Factory class attached to AnrMeasureMeasureService
 * @package MonarcFO\Service
 */
class AnrMeasureMeasureServiceFactory extends AbstractServiceFactory
{
    protected $ressources = [
        'entity' => 'MonarcFO\Model\Entity\MeasureMeasure',
        'table' => 'MonarcFO\Model\Table\MeasureMeasureTable',
        'anrTable' => 'MonarcFO\Model\Table\AnrTable',
        'userAnrTable' => 'MonarcFO\Model\Table\UserAnrTable',
        'measureTable' => 'MonarcFO\Model\Table\MeasureTable',
        // 'category' => 'MonarcFO\Model\Entity\Category'
    ];
}
