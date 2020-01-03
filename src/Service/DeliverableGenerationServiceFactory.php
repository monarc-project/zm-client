<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Service\AbstractServiceFactory;

/**
 * Factory class attached to DeliverableGenerationService
 * @package Monarc\FrontOffice\Service
 */
class DeliverableGenerationServiceFactory extends AbstractServiceFactory
{
    protected $ressources = [
        'entity' => 'Monarc\FrontOffice\Model\Entity\Delivery',
        'table' => 'Monarc\FrontOffice\Model\Table\DeliveryTable',
        'deliveryModelService' => 'Monarc\Core\Service\DeliveriesModelsService',
        'clientTable' => 'Monarc\FrontOffice\Model\Table\ClientTable',
        'anrTable' => 'Monarc\FrontOffice\Model\Table\AnrTable',
        'scaleService' => 'Monarc\FrontOffice\Service\AnrScaleService',
        'scaleTypeService' => 'Monarc\FrontOffice\Service\AnrScaleTypeService',
        'scaleCommentService' => 'Monarc\FrontOffice\Service\AnrScaleCommentService',
        'questionService' => 'Monarc\FrontOffice\Service\AnrQuestionService',
        'questionChoiceService' => 'Monarc\FrontOffice\Service\AnrQuestionChoiceService',
        'interviewService' => 'Monarc\FrontOffice\Service\AnrInterviewService',
        'threatService' => 'Monarc\FrontOffice\Service\AnrThreatService',
        'instanceService' => 'Monarc\FrontOffice\Service\AnrInstanceService',
        'recommandationService' => 'Monarc\FrontOffice\Service\AnrRecommandationService',
        'recommandationRiskService' => 'Monarc\FrontOffice\Service\AnrRecommandationRiskService',
        'recommandationHistoricService' => 'Monarc\FrontOffice\Service\AnrRecommandationHistoricService',
        'cartoRiskService' => 'Monarc\FrontOffice\Service\AnrCartoRiskService',
        'instanceRiskTable' => 'Monarc\FrontOffice\Model\Table\InstanceRiskTable',
        'instanceRiskOpTable' => 'Monarc\FrontOffice\Model\Table\InstanceRiskOpTable',
        'soaService' => 'Monarc\FrontOffice\Service\SoaService',
        'measureService' => 'Monarc\FrontOffice\Service\AnrMeasureService',
        'riskOpService' => 'Monarc\FrontOffice\Service\AnrRiskOpService',
        'riskService' => 'Monarc\FrontOffice\Service\AnrRiskService',
        'recordService' => 'Monarc\FrontOffice\Service\AnrRecordService',
        'translateService' => 'Monarc\Core\Service\TranslateService',
    ];
}
