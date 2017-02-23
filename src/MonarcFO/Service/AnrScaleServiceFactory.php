<?php
/**
 * @link      https://github.com/CASES-LU for the canonical source repository
 * @copyright Copyright (c) Cases is a registered trademark of SECURITYMADEIN.LU
 * @license   MyCases is licensed under the GNU Affero GPL v3 - See license.txt for more information
 */

namespace MonarcFO\Service;

/**
 * Factory class attached to AnrScaleService
 * @package MonarcFO\Service
 */
class AnrScaleServiceFactory extends \MonarcCore\Service\AbstractServiceFactory
{
    protected $ressources = [
        'table' => 'MonarcFO\Model\Table\ScaleTable',
        'entity' => 'MonarcFO\Model\Entity\Scale',
        'anrTable' => 'MonarcFO\Model\Table\AnrTable',
        'AnrCheckStartedService' => 'MonarcFO\Service\AnrCheckStartedService',
        'scaleImpactTypeService' => 'MonarcFO\Service\AnrScaleTypeService',
        'config' => 'MonarcCore\Service\ConfigService',
    ];
}
