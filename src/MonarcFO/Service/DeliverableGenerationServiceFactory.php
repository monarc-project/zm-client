<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;

/**
 * Factory class attached to DeliverableGenerationService
 * @package MonarcFO\Service
 */
class DeliverableGenerationServiceFactory extends AbstractServiceFactory
{
    protected $ressources = [
        'entity' => 'MonarcFO\Model\Entity\Delivery',
        'table' => 'MonarcFO\Model\Table\DeliveryTable',
        'deliveryModelService' => '\MonarcCore\Service\DeliveriesModelsService',
        'clientTable' => '\MonarcFO\Model\Table\ClientTable',
        'anrTable' => '\MonarcFO\Model\Table\AnrTable',
        'scaleService' => '\MonarcFO\Service\AnrScaleService',
        'scaleTypeService' => '\MonarcFO\Service\AnrScaleTypeService',
        'scaleCommentService' => '\MonarcFO\Service\AnrScaleCommentService',
        'questionService' => '\MonarcFO\Service\AnrQuestionService',
        'questionChoiceService' => '\MonarcFO\Service\AnrQuestionChoiceService',
        'interviewService' => '\MonarcFO\Service\AnrInterviewService',
        'threatService' => '\MonarcFO\Service\AnrThreatService',
        'instanceService' => '\MonarcFO\Service\AnrInstanceService',
        'recommandationService' => '\MonarcFO\Service\AnrRecommandationService',
        'recommandationRiskService' => '\MonarcFO\Service\AnrRecommandationRiskService',
        'recommandationHistoricService' => '\MonarcFO\Service\AnrRecommandationHistoricService',
        'cartoRiskService' => '\MonarcFO\Service\AnrCartoRiskService',
        'instanceRiskTable' => '\MonarcFO\Model\Table\InstanceRiskTable',
        'instanceRiskOpTable' => '\MonarcFO\Model\Table\InstanceRiskOpTable',
        'translateService' => 'MonarcCore\Service\TranslateService',
    ];
}
