<?php
namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;

class DeliverableGenerationServiceFactory extends AbstractServiceFactory
{
    protected $ressources = array(
        'deliveryModelService'  => '\MonarcCore\Service\DeliveriesModelsService',
        'clientTable'           => '\MonarcFO\Model\Table\ClientTable',
        'anrTable'              => '\MonarcFO\Model\Table\AnrTable',
        'scaleService'          => '\MonarcFO\Service\AnrScaleService',
        'scaleTypeService'      => '\MonarcFO\Service\AnrScaleTypeService',
        'scaleCommentService'   => '\MonarcFO\Service\AnrScaleCommentService',
        'questionService'       => '\MonarcFO\Service\AnrQuestionService',
        'questionChoiceService' => '\MonarcFO\Service\AnrQuestionChoiceService',
        'interviewService'      => '\MonarcFO\Service\AnrInterviewService',
        'threatService'         => '\MonarcFO\Service\AnrThreatService',
        'instanceService'       => '\MonarcFO\Service\AnrInstanceService',
        'recommandationService' => '\MonarcFO\Service\AnrRecommandationService',
        'recommandationRiskService' => '\MonarcFO\Service\AnrRecommandationRiskService',
        'cartoRiskService'      => '\MonarcFO\Service\AnrCartoRiskService',
        'instanceRiskTable'     => '\MonarcFO\Model\Table\InstanceRiskTable',
        'instanceRiskOpTable'   => '\MonarcFO\Model\Table\InstanceRiskOpTable',
    );
}
