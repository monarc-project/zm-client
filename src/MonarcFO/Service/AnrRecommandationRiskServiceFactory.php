<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2018 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;

/**
 * Factory class attached to AnrRecommandationRiskService
 * @package MonarcFO\Service
 */
class AnrRecommandationRiskServiceFactory extends AbstractServiceFactory
{
    protected $ressources = [
        'entity' => 'MonarcFO\Model\Entity\RecommandationRisk',
        'table' => 'MonarcFO\Model\Table\RecommandationRiskTable',
        'anrTable' => 'MonarcFO\Model\Table\AnrTable',
        'userAnrTable' => 'MonarcFO\Model\Table\UserAnrTable',
        'recommandationTable' => 'MonarcFO\Model\Table\RecommandationTable',
        'recommandationMeasureTable' => 'MonarcFO\Model\Table\RecommandationMeasureTable',
        'recommandationHistoricTable' => 'MonarcFO\Model\Table\RecommandationHistoricTable',
        'instanceRiskTable' => 'MonarcFO\Model\Table\InstanceRiskTable',
        'instanceRiskOpTable' => 'MonarcFO\Model\Table\InstanceRiskOpTable',
        'recommandationHistoricEntity' => 'MonarcFO\Model\Entity\RecommandationHistoric',
        'anrService' => 'MonarcFO\Service\AnrService',
        'anrInstanceService' => 'MonarcFO\Service\AnrInstanceService',
        'instanceTable' => '\MonarcFO\Model\Table\InstanceTable',
        'MonarcObjectTable' => '\MonarcFO\Model\Table\MonarcObjectTable',
    ];
}
