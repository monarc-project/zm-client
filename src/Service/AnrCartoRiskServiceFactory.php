<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Service\AbstractServiceFactory;

/**
 * Factory class attached to AnrCartoRiskService
 * @package Monarc\FrontOffice\Service
 */
class AnrCartoRiskServiceFactory extends AbstractServiceFactory
{
    protected $ressources = [
        'entity' => 'Monarc\FrontOffice\Model\Entity\Scale',
        'table' => 'Monarc\FrontOffice\Model\Table\ScaleTable',
        'anrTable' => 'Monarc\FrontOffice\Model\Table\AnrTable',
        'userAnrTable' => 'Monarc\FrontOffice\Model\Table\UserAnrTable',
        'instanceTable' => 'Monarc\FrontOffice\Model\Table\InstanceTable',
        'instanceRiskTable' => 'Monarc\FrontOffice\Model\Table\InstanceRiskTable',
        'instanceRiskOpTable' => 'Monarc\FrontOffice\Model\Table\InstanceRiskOpTable',
        'instanceConsequenceTable' => 'Monarc\FrontOffice\Model\Table\InstanceConsequenceTable',
        'operationalRiskScaleTable' => 'Monarc\FrontOffice\Model\Table\OperationalRiskScaleTable',
        'threatTable' => 'Monarc\FrontOffice\Model\Table\ThreatTable',
    ];
}
