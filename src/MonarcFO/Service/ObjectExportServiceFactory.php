<?php
/**
 * @link      https://github.com/CASES-LU for the canonical source repository
 * @copyright Copyright (c) Cases is a registered trademark of SECURITYMADEIN.LU
 * @license   MyCases is licensed under the GNU Affero GPL v3 - See license.txt for more information
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
        'entity' => '\MonarcFO\Model\Entity\Object',
        'assetExportService' => 'MonarcFO\Service\AssetExportService',
        'objectObjectService' => 'MonarcFO\Service\ObjectObjectService',
        'categoryTable' => '\MonarcFO\Model\Table\ObjectCategoryTable',
        'assetService' => 'MonarcFO\Service\AnrAssetService',
        'anrObjectCategoryTable' => '\MonarcFO\Model\Table\AnrObjectCategoryTable',
        'rolfTagTable' => '\MonarcFO\Model\Table\RolfTagTable',
        'rolfRiskTable' => '\MonarcFO\Model\Table\RolfRiskTable',
    ];
}
