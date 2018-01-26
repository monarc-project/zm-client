<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2018 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;

/**
 * Factory class attached to AnrThreatService
 * @package MonarcFO\Service
 */
class AnrThreatServiceFactory extends AbstractServiceFactory
{
    protected $ressources = [
        'entity' => 'MonarcFO\Model\Entity\Threat',
        'table' => 'MonarcFO\Model\Table\ThreatTable',
        'anrTable' => 'MonarcFO\Model\Table\AnrTable',
        'userAnrTable' => 'MonarcFO\Model\Table\UserAnrTable',
        'themeTable' => 'MonarcFO\Model\Table\ThemeTable',
        'instanceRiskTable' => 'MonarcFO\Model\Table\InstanceRiskTable',
        'instanceRiskService' => '\MonarcFO\Service\AnrInstanceRiskService',
    ];
}
