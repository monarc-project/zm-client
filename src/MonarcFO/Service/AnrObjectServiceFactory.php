<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2018 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;

/**
 * Factory class attached to AnrObjectService
 * @package MonarcFO\Service
 */
class AnrObjectServiceFactory extends AbstractServiceFactory
{
    protected $ressources = [
        'table' => '\MonarcFO\Model\Table\MonarcObjectTable',
        'entity' => '\MonarcFO\Model\Entity\MonarcObject',
        'anrObjectCategoryEntity' => 'MonarcFO\Model\Entity\AnrObjectCategory',
        'assetTable' => '\MonarcFO\Model\Table\AssetTable',
        'assetService' => 'MonarcFO\Service\AnrAssetService',
        'anrTable' => '\MonarcFO\Model\Table\AnrTable',
        'userAnrTable' => 'MonarcFO\Model\Table\UserAnrTable',
        'anrObjectCategoryTable' => '\MonarcFO\Model\Table\AnrObjectCategoryTable',
        'amvTable' => '\MonarcFO\Model\Table\AmvTable',
        'categoryTable' => '\MonarcFO\Model\Table\ObjectCategoryTable',
        'instanceTable' => '\MonarcFO\Model\Table\InstanceTable',
        'modelTable' => '\MonarcCore\Model\Table\ModelTable',
        'objectObjectTable' => '\MonarcFO\Model\Table\ObjectObjectTable',
        'rolfTagTable' => '\MonarcFO\Model\Table\RolfTagTable',
        'modelService' => 'MonarcCore\Service\ModelService',
        'objectObjectService' => 'MonarcFO\Service\ObjectObjectService',
        'objectExportService' => 'MonarcFO\Service\ObjectExportService',
        'selfCoreService' => 'MonarcCore\Service\ObjectService',
        'instanceRiskOpTable' => '\MonarcFO\Model\Table\InstanceRiskOpTable',
        'instanceRiskOpService' => 'MonarcFO\Service\AnrInstanceRiskOpService',
    ];
}
