<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Service;

use \MonarcCore\Service\AbstractServiceFactory;

/**
 * Anr Instance Consequence Service Factory
 *
 * Class AnrInstanceConsequenceServiceFactory
 * @package MonarcFO\Service
 */
class AnrCoreServiceFactory extends AbstractServiceFactory
{
    protected $class = "\\MonarcCore\\Service\\AnrService";

    protected $ressources = [
        'table' => 'MonarcFO\Model\Table\AnrTable',
        'entity' => 'MonarcFO\Model\Entity\Anr',
        'scaleService' => 'MonarcFO\Service\AnrScaleService',
        'anrObjectCategoryTable' => 'MonarcFO\Model\Table\AnrObjectCategoryTable',
        'instanceTable' => 'MonarcFO\Model\Table\InstanceTable',
        'instanceConsequenceTable' => 'MonarcFO\Model\Table\InstanceConsequenceTable',
        'instanceRiskTable' => 'MonarcFO\Model\Table\InstanceRiskTable',
        'instanceRiskOpTable' => 'MonarcFO\Model\Table\InstanceRiskOpTable',
        'MonarcObjectTable' => 'MonarcFO\Model\Table\MonarcObjectTable',
        'scaleTable' => 'MonarcFO\Model\Table\ScaleTable',
        'scaleImpactTypeTable' => 'MonarcFO\Model\Table\ScaleImpactTypeTable',
        'scaleCommentTable' => 'MonarcFO\Model\Table\ScaleCommentTable',
        'instanceService' => 'MonarcFO\Service\AnrInstanceService',
        'questionTable' => 'MonarcFO\Model\Table\QuestionTable',
        'questionChoiceTable' => 'MonarcFO\Model\Table\QuestionChoiceTable',
        'threatTable' => 'MonarcFO\Model\Table\ThreatTable',
        'interviewTable' => 'MonarcFO\Model\Table\InterviewTable',
        'deliveryTable' => 'MonarcFO\Model\Table\DeliveryTable',
        'referentialTable' => 'MonarcFO\Model\Table\ReferentialTable',
        'measureTable' => 'MonarcFO\Model\Table\MeasureTable',
        'measureMeasureTable' => 'MonarcFO\Model\Table\MeasureMeasureTable',
        'soaCategoryTable' => 'MonarcFO\Model\Table\SoaCategoryTable',
        'soaTable' => 'MonarcFO\Model\Table\SoaTable',
        'recordTable' => 'MonarcFO\Model\Table\RecordTable',
        'recordService' => 'MonarcFO\Service\AnrRecordService',
        'configService' => 'MonarcCore\Service\ConfigService',
    ];
}
