<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Service\AbstractServiceFactory;
use Monarc\Core\Service\TranslateService;
use Monarc\Core\Service\DeliveriesModelsService;
use Monarc\FrontOffice\Model\Table;
use Monarc\FrontOffice\Model\Entity\Delivery;
use Monarc\FrontOffice\Service;

/**
 * Factory class attached to DeliverableGenerationService
 * @package Monarc\FrontOffice\Service
 */
class DeliverableGenerationServiceFactory extends AbstractServiceFactory
{
    protected $ressources = [
        'entity' => Delivery::class,
        'table' => Table\DeliveryTable::class,
        'deliveryModelService' => DeliveriesModelsService::class,
        'clientTable' => Table\ClientTable::class,
        'anrTable' => Table\AnrTable::class,
        'scaleService' => Service\AnrScaleService::class,
        'scaleTypeService' => Service\AnrScaleTypeService::class,
        'scaleCommentService' => Service\AnrScaleCommentService::class,
        'operationalRiskScaleService' => Service\OperationalRiskScaleService::class,
        'questionService' => Service\AnrQuestionService::class,
        'questionChoiceService' => Service\AnrQuestionChoiceService::class,
        'interviewService' => Service\AnrInterviewService::class,
        'threatService' => Service\AnrThreatService::class,
        'cartoRiskService' => Service\AnrCartoRiskService::class,
        'instanceTable' => Table\InstanceTable::class,
        'instanceRiskTable' => Table\InstanceRiskTable::class,
        'instanceRiskOpTable' => Table\InstanceRiskOpTable::class,
        'soaService' => Service\SoaService::class,
        'measureService' => Service\AnrMeasureService::class,
        'anrInstanceRiskOpService' => Service\AnrInstanceRiskOpService::class,
        'anrInstanceRiskService' => Service\AnrInstanceRiskService::class,
        'recordService' => Service\AnrRecordService::class,
        'anrInstanceConsequenceService' => Service\AnrInstanceConsequenceService::class,
        'translateService' => TranslateService::class,
        'instanceRiskOwnerTable' => Table\InstanceRiskOwnerTable::class,
        'recommendationRiskTable' => Table\RecommandationRiskTable::class,
        'recommendationHistoricTable' => Table\RecommendationHistoricTable::class,
    ];
}
