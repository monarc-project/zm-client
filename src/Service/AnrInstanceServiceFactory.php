<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use \Monarc\Core\Service\AbstractServiceFactory;

/**
 * Factory class attached to AnrInstanceService
 * @package Monarc\FrontOffice\Service
 */
class AnrInstanceServiceFactory extends AbstractServiceFactory
{
    protected $ressources = [
        // Tables & Entities
        'table' => 'Monarc\FrontOffice\Model\Table\InstanceTable',
        'entity' => 'Monarc\FrontOffice\Model\Entity\Instance',
        'anrTable' => 'Monarc\FrontOffice\Model\Table\AnrTable',
        'assetTable' => 'Monarc\FrontOffice\Model\Table\AssetTable',
        'userAnrTable' => 'Monarc\FrontOffice\Model\Table\UserAnrTable',
        'amvTable' => 'Monarc\FrontOffice\Model\Table\AmvTable',
        'objectTable' => 'Monarc\FrontOffice\Model\Table\MonarcObjectTable',
        'scaleTable' => 'Monarc\FrontOffice\Model\Table\ScaleTable',
        'scaleCommentTable' => 'Monarc\FrontOffice\Model\Table\ScaleCommentTable',
        'scaleImpactTypeTable' => 'Monarc\FrontOffice\Model\Table\ScaleImpactTypeTable',
        'instanceConsequenceTable' => 'Monarc\FrontOffice\Model\Table\InstanceConsequenceTable',
        'instanceRiskTable' => 'Monarc\FrontOffice\Model\Table\InstanceRiskTable',
        'instanceConsequenceEntity' => 'Monarc\FrontOffice\Model\Entity\InstanceConsequence',
        'recommandationRiskTable' => 'Monarc\FrontOffice\Model\Table\RecommandationRiskTable',
        'recommandationTable' => 'Monarc\FrontOffice\Model\Table\RecommandationTable',
        'recommandationSetTable' => 'Monarc\FrontOffice\Model\Table\RecommandationSetTable',
        'questionTable' => 'Monarc\FrontOffice\Model\Table\QuestionTable',
        'questionChoiceTable' => 'Monarc\FrontOffice\Model\Table\QuestionChoiceTable',
        'threatTable' => 'Monarc\FrontOffice\Model\Table\ThreatTable',
        'interviewTable' => 'Monarc\FrontOffice\Model\Table\InterviewTable',
        'themeTable' => 'Monarc\FrontOffice\Model\Table\ThemeTable',
        'deliveryTable' => 'Monarc\FrontOffice\Model\Table\DeliveryTable',
        'referentialTable' => 'Monarc\FrontOffice\Model\Table\ReferentialTable',
        'soaCategoryTable' => 'Monarc\FrontOffice\Model\Table\SoaCategoryTable',
        'measureTable' => 'Monarc\FrontOffice\Model\Table\MeasureTable',
        'measureMeasureTable' => 'Monarc\FrontOffice\Model\Table\MeasureMeasureTable',
        'soaTable' => 'Monarc\FrontOffice\Model\Table\SoaTable',

        // Services
        'instanceConsequenceService' => 'Monarc\FrontOffice\Service\AnrInstanceConsequenceService',
        'instanceRiskService' => 'Monarc\FrontOffice\Service\AnrInstanceRiskService',
        'instanceRiskOpService' => 'Monarc\FrontOffice\Service\AnrInstanceRiskOpService',
        'objectObjectService' => 'Monarc\FrontOffice\Service\ObjectObjectService',
        'translateService' => 'Monarc\Core\Service\TranslateService',
        'instanceTable' => 'Monarc\FrontOffice\Model\Table\InstanceTable',
        'recordService' => 'Monarc\FrontOffice\Service\AnrRecordService',
        'configService' => 'Monarc\Core\Service\ConfigService',

        // Export (Services)
        'objectExportService' => 'Monarc\FrontOffice\Service\ObjectExportService',
        'amvService' => 'Monarc\FrontOffice\Service\AmvService',
        'scaleCommentService' => 'Monarc\FrontOffice\Service\AnrScaleCommentService',
    ];
}
