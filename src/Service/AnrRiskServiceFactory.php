<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Service\AbstractServiceFactory;
use Monarc\Core\Service\TranslateService;
use Monarc\FrontOffice\Model\Table;
use Monarc\FrontOffice\Model\Entity\InstanceRisk;

/**
 * Factory class attached to AnrRiskService
 * @package Monarc\FrontOffice\Service
 */
class AnrRiskServiceFactory extends AbstractServiceFactory
{
    protected $ressources = [
        'table' => Table\InstanceRiskTable::class,
        'entity' => InstanceRisk::class,
        'anrTable' => Table\AnrTable::class,
        'userAnrTable' => Table\UserAnrTable::class,
        'instanceTable' => Table\InstanceTable::class,
        'instanceRiskTable' => Table\InstanceRiskTable::class,
        'vulnerabilityTable' => Table\VulnerabilityTable::class,
        'threatTable' => Table\ThreatTable::class,
        'recommandationTable' => Table\RecommandationTable::class,
        'translateService' => TranslateService::class,
    ];
}
