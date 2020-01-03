<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

/**
 * Factory class attached to AnrRiskService
 * @package Monarc\FrontOffice\Service
 */
class AnrRiskServiceFactory extends \Monarc\Core\Service\AbstractServiceFactory
{
    protected $ressources = [
        'table' => 'Monarc\FrontOffice\Model\Table\InstanceRiskTable',
        'entity' => 'Monarc\FrontOffice\Model\Entity\InstanceRisk',
        'anrTable' => 'Monarc\FrontOffice\Model\Table\AnrTable',
        'userAnrTable' => 'Monarc\FrontOffice\Model\Table\UserAnrTable',
        'instanceTable' => 'Monarc\FrontOffice\Model\Table\InstanceTable',
        'instanceRiskTable' => 'Monarc\FrontOffice\Model\Table\InstanceRiskTable',
        'vulnerabilityTable' => 'Monarc\FrontOffice\Model\Table\VulnerabilityTable',
        'threatTable' => 'Monarc\FrontOffice\Model\Table\ThreatTable',
        'translateService' => 'Monarc\Core\Service\TranslateService'
    ];
}
