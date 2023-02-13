<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Table as CoreTable;
use Monarc\Core\Model\Table as CoreDeprecatedTable;
use Monarc\Core\Service\AbstractServiceFactory;
use Monarc\Core\Service\ConfigService;
use Monarc\FrontOffice\Table;
use Monarc\FrontOffice\Model\Table as DeprecatedTable;
use Monarc\FrontOffice\CronTask\Service\CronTaskService;
use Monarc\FrontOffice\Stats\Service\StatsAnrService;
use Monarc\FrontOffice\Model\Entity\Anr;

/**
 * Factory class attached to AnrService
 * @package Monarc\FrontOffice\Service
 */
class AnrServiceFactory extends AbstractServiceFactory
{
    protected $ressources = [
        'entity' => Anr::class,
        'table' => DeprecatedTable\AnrTable::class,

        //core
        'anrTable' => CoreDeprecatedTable\AnrTable::class,
        'amvTable' => CoreTable\AmvTable::class,
        'anrObjectCategoryTable' => CoreDeprecatedTable\AnrObjectCategoryTable::class,
        'assetTable' => CoreTable\AssetTable::class,
        'instanceTable' => CoreDeprecatedTable\InstanceTable::class,
        'instanceConsequenceTable' => CoreDeprecatedTable\InstanceConsequenceTable::class,
        'instanceRiskTable' => CoreDeprecatedTable\InstanceRiskTable::class,
        'instanceRiskOpTable' => CoreDeprecatedTable\InstanceRiskOpTable::class,
        'measureTable' => CoreDeprecatedTable\MeasureTable::class,
        'modelTable' => CoreTable\ModelTable::class,
        'MonarcObjectTable' => CoreDeprecatedTable\MonarcObjectTable::class,
        'objectCategoryTable' => CoreDeprecatedTable\ObjectCategoryTable::class,
        'objectObjectTable' => CoreDeprecatedTable\ObjectObjectTable::class,
        'rolfRiskTable' => CoreDeprecatedTable\RolfRiskTable::class,
        'rolfTagTable' => CoreDeprecatedTable\RolfTagTable::class,
        'scaleTable' => CoreDeprecatedTable\ScaleTable::class,
        'scaleCommentTable' => CoreDeprecatedTable\ScaleCommentTable::class,
        'scaleImpactTypeTable' => CoreDeprecatedTable\ScaleImpactTypeTable::class,
        'threatTable' => CoreTable\ThreatTable::class,
        'themeTable' => CoreTable\ThemeTable::class,
        'vulnerabilityTable' => CoreTable\VulnerabilityTable::class,
        'questionTable' => CoreDeprecatedTable\QuestionTable::class,
        'questionChoiceTable' => CoreDeprecatedTable\QuestionChoiceTable::class,
        'soaCategoryTable' => CoreDeprecatedTable\SoaCategoryTable::class,
        'referentialTable' => CoreDeprecatedTable\ReferentialTable::class,
        'operationalRiskScaleTable' => CoreTable\OperationalRiskScaleTable::class,
        'operationalRiskScaleCommentTable' => CoreTable\OperationalRiskScaleCommentTable::class,
        'translationTable' => CoreTable\TranslationTable::class,
        'anrMetadatasOnInstancesTable' => CoreTable\AnrMetadatasOnInstancesTable::class,
        'soaScaleCommentTable' => CoreTable\SoaScaleCommentTable::class,

        //fo
        'anrCliTable' => DeprecatedTable\AnrTable::class,
        'amvCliTable' => Table\AmvTable::class,
        'anrObjectCategoryCliTable' => DeprecatedTable\AnrObjectCategoryTable::class,
        'assetCliTable' => Table\AssetTable::class,
        'instanceCliTable' => DeprecatedTable\InstanceTable::class,
        'instanceConsequenceCliTable' => DeprecatedTable\InstanceConsequenceTable::class,
        'instanceRiskCliTable' => DeprecatedTable\InstanceRiskTable::class,
        'instanceRiskOpCliTable' => DeprecatedTable\InstanceRiskOpTable::class,
        'interviewCliTable' => DeprecatedTable\InterviewTable::class,
        'measureCliTable' => DeprecatedTable\MeasureTable::class,
        'objectCliTable' => DeprecatedTable\MonarcObjectTable::class,
        'objectCategoryCliTable' => DeprecatedTable\ObjectCategoryTable::class,
        'objectObjectCliTable' => DeprecatedTable\ObjectObjectTable::class,
        'recommendationTable' => DeprecatedTable\RecommandationTable::class,
        'recommandationHistoricCliTable' => DeprecatedTable\RecommendationHistoricTable::class,
        'recommandationRiskCliTable' => DeprecatedTable\RecommandationRiskTable::class,
        'recommandationSetCliTable' => DeprecatedTable\RecommandationSetTable::class,
        'rolfRiskCliTable' => DeprecatedTable\RolfRiskTable::class,
        'rolfTagCliTable' => DeprecatedTable\RolfTagTable::class,
        'scaleCliTable' => DeprecatedTable\ScaleTable::class,
        'scaleCommentCliTable' => DeprecatedTable\ScaleCommentTable::class,
        'scaleImpactTypeCliTable' => DeprecatedTable\ScaleImpactTypeTable::class,
        'snapshotCliTable' => DeprecatedTable\SnapshotTable::class,
        'threatCliTable' => Table\ThreatTable::class,
        'themeCliTable' => Table\ThemeTable::class,
        'userCliTable' => Table\UserTable::class,
        'userAnrCliTable' => Table\UserAnrTable::class,
        'vulnerabilityCliTable' => Table\VulnerabilityTable::class,
        'questionCliTable' => DeprecatedTable\QuestionTable::class,
        'questionChoiceCliTable' => DeprecatedTable\QuestionChoiceTable::class,
        'soaTable' => DeprecatedTable\SoaTable::class,
        'soaCategoryCliTable' => DeprecatedTable\SoaCategoryTable::class,
        'recordCliTable' => DeprecatedTable\RecordTable::class,
        'recordActorCliTable' => DeprecatedTable\RecordActorTable::class,
        'recordDataCategoryCliTable' => DeprecatedTable\RecordDataCategoryTable::class,
        'recordPersonalDataCliTable' => DeprecatedTable\RecordPersonalDataTable::class,
        'recordInternationalTransferCliTable' => DeprecatedTable\RecordInternationalTransferTable::class,
        'recordProcessorCliTable' => DeprecatedTable\RecordProcessorTable::class,
        'recordRecipientCliTable' => DeprecatedTable\RecordRecipientTable::class,
        'referentialCliTable' => DeprecatedTable\ReferentialTable::class,
        'measureMeasureCliTable' => DeprecatedTable\MeasureMeasureTable::class,
        'operationalRiskScaleCliTable' => Table\OperationalRiskScaleTable::class,
        'operationalRiskScaleTypeCliTable' => Table\OperationalRiskScaleTypeTable::class,
        'operationalRiskScaleCommentCliTable' => Table\OperationalRiskScaleCommentTable::class,
        'operationalInstanceRiskScaleCliTable' => Table\OperationalInstanceRiskScaleTable::class,
        'instanceRiskOwnerCliTable' => Table\InstanceRiskOwnerTable::class,
        'translationCliTable' => Table\TranslationTable::class,
        'anrMetadatasOnInstancesCliTable' => DeprecatedTable\AnrMetadatasOnInstancesTable::class,
        'instanceMetadataCliTable' => DeprecatedTable\InstanceMetadataTable::class,
        'soaScaleCommentCliTable' => DeprecatedTable\SoaScaleCommentTable::class,


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
