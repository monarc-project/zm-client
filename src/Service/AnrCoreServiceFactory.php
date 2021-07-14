<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Service\AbstractServiceFactory;
use Monarc\Core\Service\ConfigService;
use Monarc\FrontOffice\Model\Entity\Anr;
use Monarc\FrontOffice\Model\Table;

/**
 * Anr Instance Consequence Service Factory
 *
 * Class AnrCoreServiceFactory
 * @package Monarc\FrontOffice\Service
 */
class AnrCoreServiceFactory extends AbstractServiceFactory
{
    protected $class = AnrCoreService::class;

    protected $ressources = [
        'table' => Table\AnrTable::class,
        'entity' => Anr::class,
        'scaleService' => AnrScaleService::class,
        'recordService' => AnrRecordService::class,
        'configService' => ConfigService::class,
        'instanceService' => AnrInstanceService::class,
        'anrObjectCategoryTable' => Table\AnrObjectCategoryTable::class,
        'instanceTable' => Table\InstanceTable::class,
        'instanceConsequenceTable' => Table\InstanceConsequenceTable::class,
        'instanceRiskTable' => Table\InstanceRiskTable::class,
        'instanceRiskOpTable' => Table\InstanceRiskOpTable::class,
        'MonarcObjectTable' => Table\MonarcObjectTable::class,
        'scaleTable' => Table\ScaleTable::class,
        'scaleImpactTypeTable' => Table\ScaleImpactTypeTable::class,
        'scaleCommentTable' => Table\ScaleCommentTable::class,
        'questionTable' => Table\QuestionTable::class,
        'questionChoiceTable' => Table\QuestionChoiceTable::class,
        'threatTable' => Table\ThreatTable::class,
        'interviewTable' => Table\InterviewTable::class,
        'deliveryTable' => Table\DeliveryTable::class,
        'referentialTable' => Table\ReferentialTable::class,
        'measureTable' => Table\MeasureTable::class,
        'measureMeasureTable' => Table\MeasureMeasureTable::class,
        'soaCategoryTable' => Table\SoaCategoryTable::class,
        'soaTable' => Table\SoaTable::class,
        'recordTable' => Table\RecordTable::class,
        'operationalRiskScaleTable' => Table\OperationalRiskScaleTable::class,
        'translationTable' => Table\TranslationTable::class,
    ];
}
