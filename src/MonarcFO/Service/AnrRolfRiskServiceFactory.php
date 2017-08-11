<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) Cases is a registered trademark of SECURITYMADEIN.LU
 * @license   MyCases is licensed under the GNU Affero GPL v3 - See license.txt for more information
 */

namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;

/**
 * Factory class attached to AnrRolfRiskService
 * @package MonarcFO\Service
 */
class AnrRolfRiskServiceFactory extends AbstractServiceFactory
{
    protected $ressources = [
        'entity' => 'MonarcFO\Model\Entity\RolfRisk',
        'table' => 'MonarcFO\Model\Table\RolfRiskTable',
        'anrTable' => 'MonarcFO\Model\Table\AnrTable',
        'tagTable' => 'MonarcFO\Model\Table\RolfTagTable',
        'rolfTagTable' => 'MonarcFO\Model\Table\RolfTagTable',
        'objectTable' => 'MonarcFO\Model\Table\ObjectTable',
        'instanceTable' => 'MonarcFO\Model\Table\InstanceTable',
        'instanceRiskOpTable' => 'MonarcFO\Model\Table\InstanceRiskOpTable',
        'userAnrTable' => 'MonarcFO\Model\Table\UserAnrTable',
        'instanceRiskOpService' => 'MonarcFO\Service\AnrInstanceRiskOpService',
    ];
}
