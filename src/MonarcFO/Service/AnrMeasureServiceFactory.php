<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;

/**
 * Factory class attached to AnrMeasureService
 * @package MonarcFO\Service
 */
class AnrMeasureServiceFactory extends AbstractServiceFactory
{
    protected $ressources = [
        'entity' => 'MonarcFO\Model\Entity\Measure',
        'table' => 'MonarcFO\Model\Table\MeasureTable',
        'anrTable' => 'MonarcFO\Model\Table\AnrTable',
        'userAnrTable' => 'MonarcFO\Model\Table\UserAnrTable',
        'SoaEntity' => 'MonarcFO\Model\Entity\Soa',
        'SoaTable' => 'MonarcFO\Model\Table\SoaTable',
        'categoryTable' => 'MonarcFO\Model\Table\SoaCategoryTable',
        // 'category' => 'MonarcFO\Model\Entity\Category'
    ];
}
