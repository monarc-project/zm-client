<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;

/**
 * Proxy class to instantiate MonarcCore's AssetExportService, with MonarcFO's services
 * @package MonarcFO\Service
 */
class AssetExportServiceFactory extends AbstractServiceFactory
{
    protected $class = "\\MonarcCore\\Service\\AssetExportService";

    protected $ressources = [
        'table' => 'MonarcFO\Model\Table\AssetTable',
        'entity' => 'MonarcFO\Model\Entity\Asset',
        'amvService' => 'MonarcFO\Service\AmvService',
    ];
}
