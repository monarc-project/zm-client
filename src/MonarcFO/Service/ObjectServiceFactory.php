<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2018 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;

/**
 * Proxy class to instantiate MonarcCore's ObjectService, with MonarcFO's services
 * @package MonarcFO\Service
 */
class ObjectServiceFactory extends AbstractServiceFactory
{
    protected $class = "\\MonarcCore\\Service\\ObjectService";

    protected $ressources = [
        'table' => '\MonarcFO\Model\Table\MonarcObjectTable',
        'entity' => '\MonarcFO\Model\Entity\MonarcObject',
        'anrObjectCategoryEntity' => 'MonarcFO\Model\Entity\AnrObjectCategory',
        'amvTable' => '\MonarcFO\Model\Table\AmvTable',
        'anrTable' => '\MonarcFO\Model\Table\AnrTable',
        'userAnrTable' => '\MonarcFO\Model\Table\UserAnrTable',
        'anrObjectCategoryTable' => '\MonarcFO\Model\Table\AnrObjectCategoryTable',
        'assetTable' => '\MonarcFO\Model\Table\AssetTable',
        'categoryTable' => '\MonarcFO\Model\Table\ObjectCategoryTable',
        'instanceTable' => '\MonarcFO\Model\Table\InstanceTable',
        'instanceRiskOpTable' => '\MonarcFO\Model\Table\InstanceRiskOpTable',
        'objectObjectTable' => '\MonarcFO\Model\Table\ObjectObjectTable',
        'rolfTagTable' => '\MonarcFO\Model\Table\RolfTagTable',
        'assetService' => 'MonarcFO\Service\AssetService',
        'objectObjectService' => 'MonarcFO\Service\ObjectObjectService',
        'instanceRiskOpService' => 'MonarcFO\Service\AnrInstanceRiskOpService',
    ];
}
