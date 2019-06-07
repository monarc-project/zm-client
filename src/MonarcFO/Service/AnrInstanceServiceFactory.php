<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Service;

use \MonarcCore\Service\AbstractServiceFactory;

/**
 * Factory class attached to AnrInstanceService
 * @package MonarcFO\Service
 */
class AnrInstanceServiceFactory extends AbstractServiceFactory
{
    protected $ressources = [
        // Tables & Entities
        'table' => 'MonarcFO\Model\Table\InstanceTable',
        'entity' => 'MonarcFO\Model\Entity\Instance',
        'anrTable' => 'MonarcFO\Model\Table\AnrTable',
        'assetTable' => 'MonarcFO\Model\Table\AssetTable',
        'userAnrTable' => 'MonarcFO\Model\Table\UserAnrTable',
        'amvTable' => 'MonarcFO\Model\Table\AmvTable',
        'objectTable' => 'MonarcFO\Model\Table\MonarcObjectTable',
        'scaleTable' => 'MonarcFO\Model\Table\ScaleTable',
        'scaleCommentTable' => 'MonarcFO\Model\Table\ScaleCommentTable',
        'scaleImpactTypeTable' => 'MonarcFO\Model\Table\ScaleImpactTypeTable',
        'instanceConsequenceTable' => 'MonarcFO\Model\Table\InstanceConsequenceTable',
        'instanceRiskTable' => 'MonarcFO\Model\Table\InstanceRiskTable',
        'instanceConsequenceEntity' => 'MonarcFO\Model\Entity\InstanceConsequence',
        'recommandationRiskTable' => 'MonarcFO\Model\Table\RecommandationRiskTable',
        'recommandationTable' => 'MonarcFO\Model\Table\RecommandationTable',
        'questionTable' => 'MonarcFO\Model\Table\QuestionTable',
        'questionChoiceTable' => 'MonarcFO\Model\Table\QuestionChoiceTable',
        'threatTable' => 'MonarcFO\Model\Table\ThreatTable',
        'interviewTable' => 'MonarcFO\Model\Table\InterviewTable',
        'themeTable' => 'MonarcFO\Model\Table\ThemeTable',
        'deliveryTable' => 'MonarcFO\Model\Table\DeliveryTable',
        'referentialTable' => 'MonarcFO\Model\Table\ReferentialTable',
        'soaCategoryTable' => 'MonarcFO\Model\Table\SoaCategoryTable',
        'measureTable' => 'MonarcFO\Model\Table\MeasureTable',
        'measureMeasureTable' => 'MonarcFO\Model\Table\MeasureMeasureTable',
        'soaTable' => 'MonarcFO\Model\Table\SoaTable',

        // Services
        'instanceConsequenceService' => 'MonarcFO\Service\AnrInstanceConsequenceService',
        'instanceRiskService' => 'MonarcFO\Service\AnrInstanceRiskService',
        'instanceRiskOpService' => 'MonarcFO\Service\AnrInstanceRiskOpService',
        'objectObjectService' => 'MonarcFO\Service\ObjectObjectService',
        'translateService' => 'MonarcCore\Service\TranslateService',
        'instanceTable' => 'MonarcFO\Model\Table\InstanceTable',
        'recordService' => 'MonarcFO\Service\AnrRecordService',
        'configService' => 'MonarcCore\Service\ConfigService',

        // Export (Services)
        'objectExportService' => 'MonarcFO\Service\ObjectExportService',
        'amvService' => 'MonarcFO\Service\AmvService',
        'scaleCommentService' => 'MonarcFO\Service\AnrScaleCommentService',
    ];
}
