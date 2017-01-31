<?php
/**
 * @link      https://github.com/CASES-LU for the canonical source repository
 * @copyright Copyright (c) Cases is a registered trademark of SECURITYMADEIN.LU
 * @license   MyCases is licensed under the GNU Affero GPL v3 - See license.txt for more information
 */

namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;

/**
 * Asset Service Factory
 *
 * Class AssetServiceFactory
 * @package MonarcFO\Service
 */
class AssetServiceFactory extends AbstractServiceFactory
{
    protected $class = "\\MonarcCore\\Service\\AssetService";

    protected $ressources = [
        'table' => 'MonarcFO\Model\Table\AssetTable',
        'entity' => 'MonarcFO\Model\Entity\Asset',
        'anrTable' => 'MonarcFO\Model\Table\AnrTable',
        'objectTable' => 'MonarcFO\Model\Table\ObjectTable',
        'amvService' => 'MonarcFO\Service\AmvService',
    ];
}