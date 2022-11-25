<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Service\AbstractServiceFactory;
use Monarc\Core\Service\ConfigService;
use Monarc\Core\Service\OperationalRiskScalesExportService;
use Monarc\Core\Service\AnrMetadatasOnInstancesExportService;
use Monarc\Core\Service\SoaScaleCommentExportService;
use Monarc\FrontOffice\Model\Entity\Anr;
use Monarc\FrontOffice\Model\Table as DeprecatedTable;
use Monarc\FrontOffice\Table;

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
        'table' => DeprecatedTable\AnrTable::class,
        'entity' => Anr::class,
        'scaleService' => AnrScaleService::class,
        'recordService' => AnrRecordService::class,
        'configService' => ConfigService::class,
        'instanceService' => AnrInstanceService::class,
        'anrObjectCategoryTable' => DeprecatedTable\AnrObjectCategoryTable::class,
        'instanceTable' => DeprecatedTable\InstanceTable::class,
        'instanceConsequenceTable' => DeprecatedTable\InstanceConsequenceTable::class,
        'instanceRiskTable' => DeprecatedTable\InstanceRiskTable::class,
        'instanceRiskOpTable' => DeprecatedTable\InstanceRiskOpTable::class,
        'MonarcObjectTable' => DeprecatedTable\MonarcObjectTable::class,
        'scaleTable' => DeprecatedTable\ScaleTable::class,
        'scaleImpactTypeTable' => DeprecatedTable\ScaleImpactTypeTable::class,
        'scaleCommentTable' => DeprecatedTable\ScaleCommentTable::class,
        'questionTable' => DeprecatedTable\QuestionTable::class,
        'questionChoiceTable' => DeprecatedTable\QuestionChoiceTable::class,
        'threatTable' => Table\ThreatTable::class,
        'interviewTable' => DeprecatedTable\InterviewTable::class,
        'deliveryTable' => DeprecatedTable\DeliveryTable::class,
        'referentialTable' => DeprecatedTable\ReferentialTable::class,
        'measureTable' => DeprecatedTable\MeasureTable::class,
        'measureMeasureTable' => DeprecatedTable\MeasureMeasureTable::class,
        'soaCategoryTable' => DeprecatedTable\SoaCategoryTable::class,
        'soaTable' => DeprecatedTable\SoaTable::class,
        'recordTable' => DeprecatedTable\RecordTable::class,
        'operationalRiskScalesExportService' => OperationalRiskScalesExportService::class,
        'anrMetadatasOnInstancesExportService' => AnrMetadatasOnInstancesExportService::class,
        'soaScaleCommentExportService' => SoaScaleCommentExportService::class,
    ];
}
