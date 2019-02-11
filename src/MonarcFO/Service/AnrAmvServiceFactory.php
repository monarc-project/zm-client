<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;

/**
 * Factory class attached to AnrAmvService
 * @package MonarcFO\Service
 */
class AnrAmvServiceFactory extends AbstractServiceFactory
{
    protected $ressources = [
        'entity' => 'MonarcFO\Model\Entity\Amv',
        'table' => 'MonarcFO\Model\Table\AmvTable',
        'anrTable' => 'MonarcFO\Model\Table\AnrTable',
        'userAnrTable' => 'MonarcFO\Model\Table\UserAnrTable',
        'assetTable' => 'MonarcFO\Model\Table\AssetTable',
        'threatTable' => 'MonarcFO\Model\Table\ThreatTable',
        'vulnerabilityTable' => 'MonarcFO\Model\Table\VulnerabilityTable',
        'measureTable' => 'MonarcFO\Model\Table\MeasureTable',
        'referentialTable' => 'MonarcFO\Model\Table\ReferentialTable',
        'amvTable' => 'MonarcFO\Model\Table\AmvTable',
        'instanceTable' => 'MonarcFO\Model\Table\InstanceTable',
        'instanceRiskTable' => 'MonarcFO\Model\Table\InstanceRiskTable',
        'MonarcObjectTable' => 'MonarcFO\Model\Table\MonarcObjectTable',
    ];
}
