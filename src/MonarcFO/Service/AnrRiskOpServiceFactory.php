<?php
/**
 * @link      https://github.com/CASES-LU for the canonical source repository
 * @copyright Copyright (c) Cases is a registered trademark of SECURITYMADEIN.LU
 * @license   MyCases is licensed under the GNU Affero GPL v3 - See license.txt for more information
 */

namespace MonarcFO\Service;

/**
 * Anr Risk Op Service Factory
 *
 * Class AnrRiskOpServiceFactory
 * @package MonarcFO\Service
 */
class AnrRiskOpServiceFactory extends \MonarcCore\Service\AbstractServiceFactory
{
    protected $ressources = [
        'table' => 'MonarcFO\Model\Table\InstanceRiskOpTable',
        'entity' => 'MonarcFO\Model\Entity\InstanceRiskOp',
        'instanceRiskOpService' => 'MonarcFO\Service\AnrInstanceRiskOpService',
        'instanceTable' => 'MonarcFO\Model\Table\InstanceTable',
        'rolfRiskTable' => 'MonarcFO\Model\Table\RolfRiskTable',
        'rolfRiskService' => 'MonarcFO\Service\AnrRolfRiskService',
        'objectTable' => 'MonarcFO\Model\Table\ObjectTable',
        'anrTable' => 'MonarcFO\Model\Table\AnrTable',
        'userAnrTable' => 'MonarcFO\Model\Table\UserAnrTable',
    ];
}
