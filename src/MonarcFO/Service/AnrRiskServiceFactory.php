<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2018 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Service;

/**
 * Factory class attached to AnrRiskService
 * @package MonarcFO\Service
 */
class AnrRiskServiceFactory extends \MonarcCore\Service\AbstractServiceFactory
{
    protected $ressources = [
        'table' => 'MonarcFO\Model\Table\InstanceRiskTable',
        'entity' => 'MonarcFO\Model\Entity\InstanceRisk',
        'anrTable' => 'MonarcFO\Model\Table\AnrTable',
        'userAnrTable' => 'MonarcFO\Model\Table\UserAnrTable',
        'instanceTable' => 'MonarcFO\Model\Table\InstanceTable',
        'instanceRiskTable' => 'MonarcFO\Model\Table\InstanceRiskTable',
        'vulnerabilityTable' => 'MonarcFO\Model\Table\VulnerabilityTable',
        'threatTable' => 'MonarcFO\Model\Table\ThreatTable',
        'translateService' => 'MonarcCore\Service\TranslateService'
    ];
}
