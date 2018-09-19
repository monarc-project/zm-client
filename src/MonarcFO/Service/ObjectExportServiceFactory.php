<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2018 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;

/**
 * Proxy class to instantiate MonarcCore's ObjectExportService, with MonarcFO's services
 * @package MonarcFO\Service
 */
class ObjectExportServiceFactory extends AbstractServiceFactory
{
    protected $class = "\\MonarcCore\\Service\\ObjectExportService";

    protected $ressources = [
        'table' => '\MonarcFO\Model\Table\ObjectTable',
        'entity' => '\MonarcFO\Model\Entity\MonarcObject',
        'assetExportService' => 'MonarcFO\Service\AssetExportService',
        'objectObjectService' => 'MonarcFO\Service\ObjectObjectService',
        'categoryTable' => '\MonarcFO\Model\Table\ObjectCategoryTable',
        'assetService' => 'MonarcFO\Service\AnrAssetService',
        'anrObjectCategoryTable' => '\MonarcFO\Model\Table\AnrObjectCategoryTable',
        'rolfTagTable' => '\MonarcFO\Model\Table\RolfTagTable',
        'rolfRiskTable' => '\MonarcFO\Model\Table\RolfRiskTable',
        'configService' => 'MonarcCore\Service\ConfigService',
    ];
}
