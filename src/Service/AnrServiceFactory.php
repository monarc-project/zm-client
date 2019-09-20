<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Service\AbstractServiceFactory;

/**
 * Factory class attached to AnrService
 * @package Monarc\FrontOffice\Service
 */
class AnrServiceFactory extends AbstractServiceFactory
{
    protected $ressources = [
        'entity' => 'Monarc\FrontOffice\Model\Entity\Anr',
        'table' => 'Monarc\FrontOffice\Model\Table\AnrTable',

        //core
        'anrTable' => 'Monarc\Core\Model\Table\AnrTable',
        'amvTable' => 'Monarc\Core\Model\Table\AmvTable',
        'anrObjectCategoryTable' => 'Monarc\Core\Model\Table\AnrObjectCategoryTable',
        'assetTable' => 'Monarc\Core\Model\Table\AssetTable',
        'instanceTable' => 'Monarc\Core\Model\Table\InstanceTable',
        'instanceConsequenceTable' => 'Monarc\Core\Model\Table\InstanceConsequenceTable',
        'instanceRiskTable' => 'Monarc\Core\Model\Table\InstanceRiskTable',
        'instanceRiskOpTable' => 'Monarc\Core\Model\Table\InstanceRiskOpTable',
        'measureTable' => 'Monarc\Core\Model\Table\MeasureTable',
        'modelTable' => 'Monarc\Core\Model\Table\ModelTable',
        'MonarcObjectTable' => 'Monarc\Core\Model\Table\MonarcObjectTable',
        'objectCategoryTable' => 'Monarc\Core\Model\Table\ObjectCategoryTable',
        'objectObjectTable' => 'Monarc\Core\Model\Table\ObjectObjectTable',
        'rolfRiskTable' => 'Monarc\Core\Model\Table\RolfRiskTable',
        'rolfTagTable' => 'Monarc\Core\Model\Table\RolfTagTable',
        'scaleTable' => 'Monarc\Core\Model\Table\ScaleTable',
        'scaleCommentTable' => 'Monarc\Core\Model\Table\ScaleCommentTable',
        'scaleImpactTypeTable' => 'Monarc\Core\Model\Table\ScaleImpactTypeTable',
        'threatTable' => 'Monarc\Core\Model\Table\ThreatTable',
        'themeTable' => 'Monarc\Core\Model\Table\ThemeTable',
        'vulnerabilityTable' => 'Monarc\Core\Model\Table\VulnerabilityTable',
        'questionTable' => 'Monarc\Core\Model\Table\QuestionTable',
        'questionChoiceTable' => 'Monarc\Core\Model\Table\QuestionChoiceTable',
        'SoaTable' => 'Monarc\Core\Model\Table\SoaTable',
        'SoaCategoryTable' => 'Monarc\Core\Model\Table\SoaCategoryTable',
        'referentialTable' => 'Monarc\Core\Model\Table\ReferentialTable',

        //fo
        'anrCliTable' => 'Monarc\FrontOffice\Model\Table\AnrTable',
        'amvCliTable' => 'Monarc\FrontOffice\Model\Table\AmvTable',
        'anrObjectCategoryCliTable' => 'Monarc\FrontOffice\Model\Table\AnrObjectCategoryTable',
        'assetCliTable' => 'Monarc\FrontOffice\Model\Table\AssetTable',
        'instanceCliTable' => 'Monarc\FrontOffice\Model\Table\InstanceTable',
        'instanceConsequenceCliTable' => 'Monarc\FrontOffice\Model\Table\InstanceConsequenceTable',
        'instanceRiskCliTable' => 'Monarc\FrontOffice\Model\Table\InstanceRiskTable',
        'instanceRiskOpCliTable' => 'Monarc\FrontOffice\Model\Table\InstanceRiskOpTable',
        'interviewCliTable' => 'Monarc\FrontOffice\Model\Table\InterviewTable',
        'measureCliTable' => 'Monarc\FrontOffice\Model\Table\MeasureTable',
        'objectCliTable' => 'Monarc\FrontOffice\Model\Table\MonarcObjectTable',
        'objectCategoryCliTable' => 'Monarc\FrontOffice\Model\Table\ObjectCategoryTable',
        'objectObjectCliTable' => 'Monarc\FrontOffice\Model\Table\ObjectObjectTable',
        'recommandationCliTable' => 'Monarc\FrontOffice\Model\Table\RecommandationTable',
        'recommandationHistoricCliTable' => 'Monarc\FrontOffice\Model\Table\RecommandationHistoricTable',
        'recommandationRiskCliTable' => 'Monarc\FrontOffice\Model\Table\RecommandationRiskTable',
        'recommandationSetCliTable' => 'Monarc\FrontOffice\Model\Table\RecommandationSetTable',
        'rolfRiskCliTable' => 'Monarc\FrontOffice\Model\Table\RolfRiskTable',
        'rolfTagCliTable' => 'Monarc\FrontOffice\Model\Table\RolfTagTable',
        'scaleCliTable' => 'Monarc\FrontOffice\Model\Table\ScaleTable',
        'scaleCommentCliTable' => 'Monarc\FrontOffice\Model\Table\ScaleCommentTable',
        'scaleImpactTypeCliTable' => 'Monarc\FrontOffice\Model\Table\ScaleImpactTypeTable',
        'snapshotCliTable' => 'Monarc\FrontOffice\Model\Table\SnapshotTable',
        'threatCliTable' => 'Monarc\FrontOffice\Model\Table\ThreatTable',
        'themeCliTable' => 'Monarc\FrontOffice\Model\Table\ThemeTable',
        'userCliTable' => 'Monarc\FrontOffice\Model\Table\UserTable',
        'userAnrCliTable' => 'Monarc\FrontOffice\Model\Table\UserAnrTable',
        'vulnerabilityCliTable' => 'Monarc\FrontOffice\Model\Table\VulnerabilityTable',
        'questionCliTable' => 'Monarc\FrontOffice\Model\Table\QuestionTable',
        'questionChoiceCliTable' => 'Monarc\FrontOffice\Model\Table\QuestionChoiceTable',
        'SoaCliTable' => 'Monarc\FrontOffice\Model\Table\SoaTable',
        'SoaCategoryCliTable' => 'Monarc\FrontOffice\Model\Table\SoaCategoryTable',
        'recordCliTable' => 'Monarc\FrontOffice\Model\Table\RecordTable',
        'recordActorCliTable' => 'Monarc\FrontOffice\Model\Table\RecordActorTable',
        'recordDataCategoryCliTable' => 'Monarc\FrontOffice\Model\Table\RecordDataCategoryTable',
        'recordPersonalDataCliTable' => 'Monarc\FrontOffice\Model\Table\RecordPersonalDataTable',
        'recordInternationalTransferCliTable' => 'Monarc\FrontOffice\Model\Table\RecordInternationalTransferTable',
        'recordProcessorCliTable' => 'Monarc\FrontOffice\Model\Table\RecordProcessorTable',
        'recordRecipientCliTable' => 'Monarc\FrontOffice\Model\Table\RecordRecipientTable',
        'referentialCliTable' => 'Monarc\FrontOffice\Model\Table\ReferentialTable',
        'measureMeasureCliTable' => 'Monarc\FrontOffice\Model\Table\MeasureMeasureTable',


        // export
        'instanceService' => '\Monarc\FrontOffice\Service\AnrInstanceService',
        'recordService' => '\Monarc\FrontOffice\Service\AnrRecordService',
        'recordProcessorService' => '\Monarc\FrontOffice\Service\AnrRecordProcessorService',

    ];
}
