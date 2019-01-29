<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;

/**
 * Factory class attached to AnrCheckStartedService
 * @package MonarcFO\Service
 */
class AnrCheckStartedServiceFactory extends AbstractServiceFactory
{
    protected $ressources = [
        'entity' => 'MonarcFO\Model\Entity\Anr',
        'table' => 'MonarcFO\Model\Table\AnrTable',
        'modelTable' => 'MonarcCore\Model\Table\ModelTable',
        'instanceRiskTable' => 'MonarcFO\Model\Table\InstanceRiskTable',
        'instanceConsequenceTable' => 'MonarcFO\Model\Table\InstanceConsequenceTable',
        'threatTable' => 'MonarcFO\Model\Table\ThreatTable',
        'instanceRiskOpTable' => 'MonarcFO\Model\Table\InstanceRiskOpTable',
    ];
}
