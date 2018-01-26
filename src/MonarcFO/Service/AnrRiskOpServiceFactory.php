<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2018 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Service;

/**
 * Factory class attached to AnrRiskOpService
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
        'translateService' => 'MonarcCore\Service\TranslateService'
    ];
}
