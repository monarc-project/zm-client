<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Service\AbstractServiceFactory;

/**
 * Factory class attached to AnrRecommandationRiskService
 * @package Monarc\FrontOffice\Service
 */
class AnrRecommandationRiskServiceFactory extends AbstractServiceFactory
{
    protected $ressources = [
        'entity' => 'Monarc\FrontOffice\Model\Entity\RecommandationRisk',
        'table' => 'Monarc\FrontOffice\Model\Table\RecommandationRiskTable',
        'anrTable' => 'Monarc\FrontOffice\Model\Table\AnrTable',
        'userAnrTable' => 'Monarc\FrontOffice\Model\Table\UserAnrTable',
        'recommandationTable' => 'Monarc\FrontOffice\Model\Table\RecommandationTable',
        'recommandationHistoricTable' => 'Monarc\FrontOffice\Model\Table\RecommandationHistoricTable',
        'instanceRiskTable' => 'Monarc\FrontOffice\Model\Table\InstanceRiskTable',
        'instanceRiskOpTable' => 'Monarc\FrontOffice\Model\Table\InstanceRiskOpTable',
        'recommandationHistoricEntity' => 'Monarc\FrontOffice\Model\Entity\RecommandationHistoric',
        'anrService' => 'Monarc\FrontOffice\Service\AnrService',
        'anrInstanceService' => 'Monarc\FrontOffice\Service\AnrInstanceService',
        'instanceTable' => '\Monarc\FrontOffice\Model\Table\InstanceTable',
        'MonarcObjectTable' => '\Monarc\FrontOffice\Model\Table\MonarcObjectTable',
    ];
}
