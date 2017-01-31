<?php
/**
 * @link      https://github.com/CASES-LU for the canonical source repository
 * @copyright Copyright (c) Cases is a registered trademark of SECURITYMADEIN.LU
 * @license   MyCases is licensed under the GNU Affero GPL v3 - See license.txt for more information
 */

namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;

/**
 * Amv Service Factory
 *
 * Class AmvServiceFactory
 * @package MonarcFO\Service
 */
class AmvServiceFactory extends AbstractServiceFactory
{
    protected $class = "\\MonarcCore\\Service\\AmvService";

    protected $ressources = [
        'table' => 'MonarcFO\Model\Table\AmvTable',
        'entity' => 'MonarcFO\Model\Entity\Amv',
        'anrTable' => '\MonarcFO\Model\Table\AnrTable',
        'assetTable' => '\MonarcFO\Model\Table\AssetTable',
        'instanceTable' => 'MonarcCore\Model\Table\InstanceTable',
        'measureTable' => '\MonarcFO\Model\Table\MeasureTable',
        'threatTable' => '\MonarcFO\Model\Table\ThreatTable',
        'vulnerabilityTable' => '\MonarcFO\Model\Table\VulnerabilityTable',
    ];
}
