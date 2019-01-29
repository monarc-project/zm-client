<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;

/**
 * Proxy class to instantiate MonarcCore's AssetService, with MonarcFO's services
 * @package MonarcFO\Service
 */
class AssetServiceFactory extends AbstractServiceFactory
{
    protected $class = "\\MonarcCore\\Service\\AssetService";

    protected $ressources = [
        'table' => 'MonarcFO\Model\Table\AssetTable',
        'entity' => 'MonarcFO\Model\Entity\Asset',
        'anrTable' => 'MonarcFO\Model\Table\AnrTable',
        'MonarcObjectTable' => 'MonarcFO\Model\Table\MonarcObjectTable',
        'amvService' => 'MonarcFO\Service\AmvService',
    ];
}