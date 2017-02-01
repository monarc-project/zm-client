<?php
/**
 * @link      https://github.com/CASES-LU for the canonical source repository
 * @copyright Copyright (c) Cases is a registered trademark of SECURITYMADEIN.LU
 * @license   MyCases is licensed under the GNU Affero GPL v3 - See license.txt for more information
 */

namespace MonarcFO\Service;

/**
 * Anr Scale Type Service Factory
 *
 * Class AnrScaleTypeServiceFactory
 * @package MonarcFO\Service
 */
class AnrScaleTypeServiceFactory extends \MonarcCore\Service\AbstractServiceFactory
{
    protected $ressources = [
        'table' => 'MonarcFO\Model\Table\ScaleImpactTypeTable',
        'entity' => 'MonarcFO\Model\Entity\ScaleImpactType',
        'anrTable' => 'MonarcFO\Model\Table\AnrTable',
        'userAnrTable' => 'MonarcFO\Model\Table\UserAnrTable',
        'scaleTable' => 'MonarcFO\Model\Table\ScaleTable',
        'instanceTable' => 'MonarcFO\Model\Table\InstanceTable',
        'instanceConsequenceService' => 'MonarcFO\Service\AnrInstanceConsequenceService'
    ];
}
