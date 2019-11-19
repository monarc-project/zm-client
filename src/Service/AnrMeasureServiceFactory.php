<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Service\AbstractServiceFactory;

/**
 * Factory class attached to AnrMeasureService
 * @package Monarc\FrontOffice\Service
 */
class AnrMeasureServiceFactory extends AbstractServiceFactory
{
    protected $ressources = [
        'entity' => 'Monarc\FrontOffice\Model\Entity\Measure',
        'table' => 'Monarc\FrontOffice\Model\Table\MeasureTable',
        'anrTable' => 'Monarc\FrontOffice\Model\Table\AnrTable',
        'userAnrTable' => 'Monarc\FrontOffice\Model\Table\UserAnrTable',
        'SoaEntity' => 'Monarc\FrontOffice\Model\Entity\Soa',
        'SoaTable' => 'Monarc\FrontOffice\Model\Table\SoaTable',
        'categoryTable' => 'Monarc\FrontOffice\Model\Table\SoaCategoryTable',
        // 'category' => 'Monarc\FrontOffice\Model\Entity\Category'
    ];
}
