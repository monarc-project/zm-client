<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Service\AbstractServiceFactory;

/**
 * Proxy class to instantiate Monarc\Core's ObjectService, with Monarc\FrontOffice's services
 * @package Monarc\FrontOffice\Service
 */
class ObjectServiceFactory extends AbstractServiceFactory
{
    protected $class = "\\Monarc\Core\\Service\\ObjectService";

    protected $ressources = [
        'table' => '\Monarc\FrontOffice\Model\Table\MonarcObjectTable',
        'entity' => '\Monarc\FrontOffice\Model\Entity\MonarcObject',
        'anrObjectCategoryEntity' => 'Monarc\FrontOffice\Model\Entity\AnrObjectCategory',
        'amvTable' => '\Monarc\FrontOffice\Model\Table\AmvTable',
        'anrTable' => '\Monarc\FrontOffice\Model\Table\AnrTable',
        'userAnrTable' => '\Monarc\FrontOffice\Model\Table\UserAnrTable',
        'anrObjectCategoryTable' => '\Monarc\FrontOffice\Model\Table\AnrObjectCategoryTable',
        'assetTable' => '\Monarc\FrontOffice\Model\Table\AssetTable',
        'categoryTable' => '\Monarc\FrontOffice\Model\Table\ObjectCategoryTable',
        'instanceTable' => '\Monarc\FrontOffice\Model\Table\InstanceTable',
        'instanceRiskOpTable' => '\Monarc\FrontOffice\Model\Table\InstanceRiskOpTable',
        'objectObjectTable' => '\Monarc\FrontOffice\Model\Table\ObjectObjectTable',
        'rolfTagTable' => '\Monarc\FrontOffice\Model\Table\RolfTagTable',
        'assetService' => 'Monarc\FrontOffice\Service\AssetService',
        'objectObjectService' => 'Monarc\FrontOffice\Service\ObjectObjectService',
        'instanceRiskOpService' => 'Monarc\FrontOffice\Service\AnrInstanceRiskOpService',
    ];
}
