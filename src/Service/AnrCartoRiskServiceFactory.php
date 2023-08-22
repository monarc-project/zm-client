<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Service\AbstractServiceFactory;
use Monarc\FrontOffice\Table\UserAnrTable;
use Monarc\FrontOffice\Table\AnrTable;

/**
 * Factory class attached to AnrCartoRiskService
 * @package Monarc\FrontOffice\Service
 */
class AnrCartoRiskServiceFactory extends AbstractServiceFactory
{
    protected $ressources = [
        'entity' => 'Monarc\FrontOffice\Model\Entity\Scale',
        'table' => 'Monarc\FrontOffice\Model\Table\ScaleTable',
        'anrTable' => AnrTable::class,
        'userAnrTable' => UserAnrTable::class,
        'instanceTable' => 'Monarc\FrontOffice\Model\Table\InstanceTable',
        'instanceRiskTable' => 'Monarc\FrontOffice\Model\Table\InstanceRiskTable',
        'instanceRiskOpTable' => 'Monarc\FrontOffice\Model\Table\InstanceRiskOpTable',
        'instanceConsequenceTable' => 'Monarc\FrontOffice\Model\Table\InstanceConsequenceTable',
        'operationalRiskScaleTable' => 'Monarc\FrontOffice\Table\OperationalRiskScaleTable',
    ];
}
