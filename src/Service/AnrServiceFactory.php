<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Model\Table as CoreTable;
use Monarc\Core\Service\AbstractServiceFactory;
use Monarc\Core\Service\ConfigService;
use Monarc\FrontOffice\CronTask\Service\CronTaskService;
use Monarc\FrontOffice\Model\Table;
use Monarc\FrontOffice\Stats\Service\StatsAnrService;
use Monarc\FrontOffice\Model\Entity\Anr;
use Monarc\FrontOffice\Service\AnrInstanceService;
use Monarc\FrontOffice\Service\AnrRecordService;
use Monarc\FrontOffice\Service\AnrRecordProcessorService;

/**
 * Factory class attached to AnrService
 * @package Monarc\FrontOffice\Service
 */
class AnrServiceFactory extends AbstractServiceFactory
{
    protected $ressources = [
        'entity' => Anr::class,
        'table' => Table\AnrTable::class,

        //core
        'anrTable' => CoreTable\AnrTable::class,
        'amvTable' => CoreTable\AmvTable::class,
        'anrObjectCategoryTable' => CoreTable\AnrObjectCategoryTable::class,
        'assetTable' => CoreTable\AssetTable::class,
        'instanceTable' => CoreTable\InstanceTable::class,
        'instanceConsequenceTable' => CoreTable\InstanceConsequenceTable::class,
        'instanceRiskTable' => CoreTable\InstanceRiskTable::class,
        'instanceRiskOpTable' => CoreTable\InstanceRiskOpTable::class,
        'measureTable' => CoreTable\MeasureTable::class,
        'modelTable' => CoreTable\ModelTable::class,
        'MonarcObjectTable' => CoreTable\MonarcObjectTable::class,
        'objectCategoryTable' => CoreTable\ObjectCategoryTable::class,
        'objectObjectTable' => CoreTable\ObjectObjectTable::class,
        'rolfRiskTable' => CoreTable\RolfRiskTable::class,
        'rolfTagTable' => CoreTable\RolfTagTable::class,
        'scaleTable' => CoreTable\ScaleTable::class,
        'scaleCommentTable' => CoreTable\ScaleCommentTable::class,
        'scaleImpactTypeTable' => CoreTable\ScaleImpactTypeTable::class,
        'threatTable' => CoreTable\ThreatTable::class,
        'themeTable' => CoreTable\ThemeTable::class,
        'vulnerabilityTable' => CoreTable\VulnerabilityTable::class,
        'questionTable' => CoreTable\QuestionTable::class,
        'questionChoiceTable' => CoreTable\QuestionChoiceTable::class,
        'soaCategoryTable' => CoreTable\SoaCategoryTable::class,
        'referentialTable' => CoreTable\ReferentialTable::class,
        'operationalRiskScaleTable' => CoreTable\OperationalRiskScaleTable::class,
        'operationalRiskScaleCommentTable' => CoreTable\OperationalRiskScaleCommentTable::class,
        'translationTable' => CoreTable\TranslationTable::class,
        'anrMetadatasOnInstancesTable' => CoreTable\AnrMetadatasOnInstancesTable::class,
        'soaScaleCommentTable' => CoreTable\SoaScaleCommentTable::class,

        //fo
        'anrCliTable' => Table\AnrTable::class,
        'amvCliTable' => Table\AmvTable::class,
        'anrObjectCategoryCliTable' => Table\AnrObjectCategoryTable::class,
        'assetCliTable' => Table\AssetTable::class,
        'instanceCliTable' => Table\InstanceTable::class,
        'instanceConsequenceCliTable' => Table\InstanceConsequenceTable::class,
        'instanceRiskCliTable' => Table\InstanceRiskTable::class,
        'instanceRiskOpCliTable' => Table\InstanceRiskOpTable::class,
        'interviewCliTable' => Table\InterviewTable::class,
        'measureCliTable' => Table\MeasureTable::class,
        'objectCliTable' => Table\MonarcObjectTable::class,
        'objectCategoryCliTable' => Table\ObjectCategoryTable::class,
        'objectObjectCliTable' => Table\ObjectObjectTable::class,
        'recommendationTable' => Table\RecommandationTable::class,
        'recommandationHistoricCliTable' => Table\RecommendationHistoricTable::class,
        'recommandationRiskCliTable' => Table\RecommandationRiskTable::class,
        'recommandationSetCliTable' => Table\RecommandationSetTable::class,
        'rolfRiskCliTable' => Table\RolfRiskTable::class,
        'rolfTagCliTable' => Table\RolfTagTable::class,
        'scaleCliTable' => Table\ScaleTable::class,
        'scaleCommentCliTable' => Table\ScaleCommentTable::class,
        'scaleImpactTypeCliTable' => Table\ScaleImpactTypeTable::class,
        'snapshotCliTable' => Table\SnapshotTable::class,
        'threatCliTable' => Table\ThreatTable::class,
        'themeCliTable' => Table\ThemeTable::class,
        'userCliTable' => Table\UserTable::class,
        'userAnrCliTable' => Table\UserAnrTable::class,
        'vulnerabilityCliTable' => Table\VulnerabilityTable::class,
        'questionCliTable' => Table\QuestionTable::class,
        'questionChoiceCliTable' => Table\QuestionChoiceTable::class,
        'soaTable' => Table\SoaTable::class,
        'soaCategoryCliTable' => Table\SoaCategoryTable::class,
        'recordCliTable' => Table\RecordTable::class,
        'recordActorCliTable' => Table\RecordActorTable::class,
        'recordDataCategoryCliTable' => Table\RecordDataCategoryTable::class,
        'recordPersonalDataCliTable' => Table\RecordPersonalDataTable::class,
        'recordInternationalTransferCliTable' => Table\RecordInternationalTransferTable::class,
        'recordProcessorCliTable' => Table\RecordProcessorTable::class,
        'recordRecipientCliTable' => Table\RecordRecipientTable::class,
        'referentialCliTable' => Table\ReferentialTable::class,
        'measureMeasureCliTable' => Table\MeasureMeasureTable::class,
        'operationalRiskScaleCliTable' => Table\OperationalRiskScaleTable::class,
        'operationalRiskScaleTypeCliTable' => Table\OperationalRiskScaleTypeTable::class,
        'operationalRiskScaleCommentCliTable' => Table\OperationalRiskScaleCommentTable::class,
        'operationalInstanceRiskScaleCliTable' => Table\OperationalInstanceRiskScaleTable::class,
        'instanceRiskOwnerCliTable' => Table\InstanceRiskOwnerTable::class,
        'translationCliTable' => Table\TranslationTable::class,
        'anrMetadatasOnInstancesCliTable' => Table\AnrMetadatasOnInstancesTable::class,
        'instanceMetadataCliTable' => Table\InstanceMetadataTable::class,
        'soaScaleCommentCliTable' => Table\SoaScaleCommentTable::class,


        // export
        'instanceService' => AnrInstanceService::class,
        'recordService' => AnrRecordService::class,
        'recordProcessorService' => AnrRecordProcessorService::class,

        // Stats Service
        'statsAnrService' => StatsAnrService::class,

        // other Service
        'configService' => ConfigService::class,
        'cronTaskService' => CronTaskService::class,
    ];
}
