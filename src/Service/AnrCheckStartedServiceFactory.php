<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Service\AbstractServiceFactory;

/**
 * Factory class attached to AnrCheckStartedService
 * @package Monarc\FrontOffice\Service
 */
class AnrCheckStartedServiceFactory extends AbstractServiceFactory
{
    protected $ressources = [
        'entity' => 'Monarc\FrontOffice\Model\Entity\Anr',
        'table' => 'Monarc\FrontOffice\Model\Table\AnrTable',
        'modelTable' => 'Monarc\Core\Model\Table\ModelTable',
        'instanceRiskTable' => 'Monarc\FrontOffice\Model\Table\InstanceRiskTable',
        'instanceConsequenceTable' => 'Monarc\FrontOffice\Model\Table\InstanceConsequenceTable',
        'threatTable' => 'Monarc\FrontOffice\Model\Table\ThreatTable',
        'instanceRiskOpTable' => 'Monarc\FrontOffice\Model\Table\InstanceRiskOpTable',
    ];
}
