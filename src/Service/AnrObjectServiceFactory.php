<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Service\AbstractServiceFactory;

/**
 * Factory class attached to AnrObjectService
 * @package Monarc\FrontOffice\Service
 */
class AnrObjectServiceFactory extends AbstractServiceFactory
{
    protected $ressources = [
        'table' => 'Monarc\FrontOffice\Model\Table\MonarcObjectTable',
        'entity' => 'Monarc\FrontOffice\Model\Entity\MonarcObject',
        'anrObjectCategoryEntity' => 'Monarc\FrontOffice\Model\Entity\AnrObjectCategory',
        'assetTable' => 'Monarc\FrontOffice\Model\Table\AssetTable',
        'assetService' => 'Monarc\FrontOffice\Service\AnrAssetService',
        'anrTable' => 'Monarc\FrontOffice\Model\Table\AnrTable',
        'userAnrTable' => 'Monarc\FrontOffice\Model\Table\UserAnrTable',
        'anrObjectCategoryTable' => 'Monarc\FrontOffice\Model\Table\AnrObjectCategoryTable',
        'amvTable' => 'Monarc\FrontOffice\Model\Table\AmvTable',
        'categoryTable' => 'Monarc\FrontOffice\Model\Table\ObjectCategoryTable',
        'instanceTable' => 'Monarc\FrontOffice\Model\Table\InstanceTable',
        'modelTable' => 'Monarc\Core\Model\Table\ModelTable',
        'objectObjectTable' => 'Monarc\FrontOffice\Model\Table\ObjectObjectTable',
        'rolfTagTable' => 'Monarc\FrontOffice\Model\Table\RolfTagTable',
        'modelService' => 'Monarc\Core\Service\ModelService',
        'objectObjectService' => 'Monarc\FrontOffice\Service\ObjectObjectService',
        'objectExportService' => 'Monarc\FrontOffice\Service\ObjectExportService',
        'selfCoreService' => 'Monarc\Core\Service\ObjectService',
        'instanceRiskOpTable' => 'Monarc\FrontOffice\Model\Table\InstanceRiskOpTable',
        'instanceRiskOpService' => 'Monarc\FrontOffice\Service\AnrInstanceRiskOpService',
    ];
}
