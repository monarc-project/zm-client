<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Service\AbstractServiceFactory;

/**
 * Proxy factory class to instantiate Monarc\Core's AmvService using Monarc\FrontOffice's services
 * @see \Monarc\Core\Service\AmvService
 * @package Monarc\FrontOffice\Service
 */
class AmvServiceFactory extends AbstractServiceFactory
{
    protected $class = AmvService::class;

    protected $ressources = [
        'table' => 'Monarc\FrontOffice\Model\Table\AmvTable',
        'entity' => 'Monarc\FrontOffice\Model\Entity\Amv',
        'anrTable' => 'Monarc\FrontOffice\Model\Table\AnrTable',
        'assetTable' => 'Monarc\FrontOffice\Model\Table\AssetTable',
        'instanceTable' => 'Monarc\Core\Model\Table\InstanceTable',
        'measureTable' => 'Monarc\FrontOffice\Model\Table\MeasureTable',
        'threatTable' => 'Monarc\FrontOffice\Model\Table\ThreatTable',
        'vulnerabilityTable' => 'Monarc\FrontOffice\Model\Table\VulnerabilityTable',
    ];
}
