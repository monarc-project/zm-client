<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use \Monarc\Core\Service\AbstractServiceFactory;

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
        'table' => 'Monarc\FrontOffice\Model\Table\AnrTable',
        'entity' => 'Monarc\FrontOffice\Model\Entity\Anr',
        'scaleService' => 'Monarc\FrontOffice\Service\AnrScaleService',
        'anrObjectCategoryTable' => 'Monarc\FrontOffice\Model\Table\AnrObjectCategoryTable',
        'instanceTable' => 'Monarc\FrontOffice\Model\Table\InstanceTable',
        'instanceConsequenceTable' => 'Monarc\FrontOffice\Model\Table\InstanceConsequenceTable',
        'instanceRiskTable' => 'Monarc\FrontOffice\Model\Table\InstanceRiskTable',
        'instanceRiskOpTable' => 'Monarc\FrontOffice\Model\Table\InstanceRiskOpTable',
        'MonarcObjectTable' => 'Monarc\FrontOffice\Model\Table\MonarcObjectTable',
        'scaleTable' => 'Monarc\FrontOffice\Model\Table\ScaleTable',
        'scaleImpactTypeTable' => 'Monarc\FrontOffice\Model\Table\ScaleImpactTypeTable',
        'scaleCommentTable' => 'Monarc\FrontOffice\Model\Table\ScaleCommentTable',
        'instanceService' => 'Monarc\FrontOffice\Service\AnrInstanceService',
        'questionTable' => 'Monarc\FrontOffice\Model\Table\QuestionTable',
        'questionChoiceTable' => 'Monarc\FrontOffice\Model\Table\QuestionChoiceTable',
        'threatTable' => 'Monarc\FrontOffice\Model\Table\ThreatTable',
        'interviewTable' => 'Monarc\FrontOffice\Model\Table\InterviewTable',
        'deliveryTable' => 'Monarc\FrontOffice\Model\Table\DeliveryTable',
        'referentialTable' => 'Monarc\FrontOffice\Model\Table\ReferentialTable',
        'measureTable' => 'Monarc\FrontOffice\Model\Table\MeasureTable',
        'measureMeasureTable' => 'Monarc\FrontOffice\Model\Table\MeasureMeasureTable',
        'soaCategoryTable' => 'Monarc\FrontOffice\Model\Table\SoaCategoryTable',
        'soaTable' => 'Monarc\FrontOffice\Model\Table\SoaTable',
        'recordTable' => 'Monarc\FrontOffice\Model\Table\RecordTable',
        'recordService' => 'Monarc\FrontOffice\Service\AnrRecordService',
        'configService' => 'Monarc\Core\Service\ConfigService',
        'operationalRiskScaleTable' => 'Monarc\FrontOffice\Model\Table\OperationalRiskScaleTable',
        'operationalRiskScaleCommentTable' => 'Monarc\FrontOffice\Model\Table\OperationalRiskScaleCommentTable',
        'translationTable' => 'Monarc\FrontOffice\Model\Table\TranslationTable',
    ];
}
