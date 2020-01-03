<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Service\AbstractServiceFactory;

/**
 * Proxy class to instantiate Monarc\Core's ObjectExportService, with Monarc\FrontOffice's services
 * @package Monarc\FrontOffice\Service
 */
class ObjectExportServiceFactory extends AbstractServiceFactory
{
    protected $class = "\\Monarc\Core\\Service\\ObjectExportService";

    protected $ressources = [
        'table' => 'Monarc\FrontOffice\Model\Table\MonarcObjectTable',
        'entity' => 'Monarc\FrontOffice\Model\Entity\MonarcObject',
        'assetExportService' => 'Monarc\FrontOffice\Service\AssetExportService',
        'objectObjectService' => 'Monarc\FrontOffice\Service\ObjectObjectService',
        'categoryTable' => 'Monarc\FrontOffice\Model\Table\ObjectCategoryTable',
        'assetService' => 'Monarc\FrontOffice\Service\AnrAssetService',
        'anrObjectCategoryTable' => 'Monarc\FrontOffice\Model\Table\AnrObjectCategoryTable',
        'rolfTagTable' => 'Monarc\FrontOffice\Model\Table\RolfTagTable',
        'rolfRiskTable' => 'Monarc\FrontOffice\Model\Table\RolfRiskTable',
        'measureTable' => 'Monarc\FrontOffice\Model\Table\MeasureTable',
        'configService' => 'Monarc\Core\Service\ConfigService',
    ];
}
