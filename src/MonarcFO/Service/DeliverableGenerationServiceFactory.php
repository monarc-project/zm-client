<?php
/**
 * @link      https://github.com/CASES-LU for the canonical source repository
 * @copyright Copyright (c) Cases is a registered trademark of SECURITYMADEIN.LU
 * @license   MyCases is licensed under the GNU Affero GPL v3 - See license.txt for more information
 */

namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;

/**
 * Deliverable Generation Service Factory
 *
 * Class DeliverableGenerationServiceFactory
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
        'cartoRiskService' => '\MonarcFO\Service\AnrCartoRiskService',
        'instanceRiskTable' => '\MonarcFO\Model\Table\InstanceRiskTable',
        'instanceRiskOpTable' => '\MonarcFO\Model\Table\InstanceRiskOpTable',
        'translateService' => 'MonarcCore\Service\TranslateService',
    ];
}