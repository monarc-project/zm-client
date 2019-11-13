<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Service\AbstractServiceFactory;

/**
 * Factory class attached to AnrMeasureMeasureService
 * @package Monarc\FrontOffice\Service
 */
class AnrMeasureMeasureServiceFactory extends AbstractServiceFactory
{
    protected $ressources = [
        'entity' => 'Monarc\FrontOffice\Model\Entity\MeasureMeasure',
        'table' => 'Monarc\FrontOffice\Model\Table\MeasureMeasureTable',
        'anrTable' => 'Monarc\FrontOffice\Model\Table\AnrTable',
        'userAnrTable' => 'Monarc\FrontOffice\Model\Table\UserAnrTable',
        'measureTable' => 'Monarc\FrontOffice\Model\Table\MeasureTable',
        // 'category' => 'Monarc\FrontOffice\Model\Entity\Category'
    ];
}
