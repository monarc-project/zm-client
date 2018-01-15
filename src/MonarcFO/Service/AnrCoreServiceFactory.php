<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) Cases is a registered trademark of SECURITYMADEIN.LU
 * @license   MyCases is licensed under the GNU Affero GPL v3 - See license.txt for more information
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
        'objectTable' => 'MonarcFO\Model\Table\ObjectTable',
        'scaleTable' => 'MonarcFO\Model\Table\ScaleTable',
        'scaleImpactTypeTable' => 'MonarcFO\Model\Table\ScaleImpactTypeTable',
        'scaleCommentTable' => 'MonarcFO\Model\Table\ScaleCommentTable',
        'instanceService' => 'MonarcFO\Service\AnrInstanceService',
        'questionTable' => 'MonarcFO\Model\Table\QuestionTable',
        'questionChoiceTable' => 'MonarcFO\Model\Table\QuestionChoiceTable',
        'threatTable' => 'MonarcFO\Model\Table\ThreatTable',
        'interviewTable' => 'MonarcFO\Model\Table\InterviewTable',
    ];
}
