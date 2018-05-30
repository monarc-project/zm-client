<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2018 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;

/**
 * Factory class attached to AnrService
 * @package MonarcFO\Service
 */
class AnrServiceFactory extends AbstractServiceFactory
{
    protected $ressources = [
        'entity' => 'MonarcFO\Model\Entity\Anr',
        'table' => 'MonarcFO\Model\Table\AnrTable',

        //core
        'anrTable' => 'MonarcCore\Model\Table\AnrTable',
        'amvTable' => 'MonarcCore\Model\Table\AmvTable',
        'anrObjectCategoryTable' => 'MonarcCore\Model\Table\AnrObjectCategoryTable',
        'assetTable' => 'MonarcCore\Model\Table\AssetTable',
        'instanceTable' => 'MonarcCore\Model\Table\InstanceTable',
        'instanceConsequenceTable' => 'MonarcCore\Model\Table\InstanceConsequenceTable',
        'instanceRiskTable' => 'MonarcCore\Model\Table\InstanceRiskTable',
        'instanceRiskOpTable' => 'MonarcCore\Model\Table\InstanceRiskOpTable',
        'measureTable' => 'MonarcCore\Model\Table\MeasureTable',
        'modelTable' => 'MonarcCore\Model\Table\ModelTable',
        'objectTable' => 'MonarcCore\Model\Table\ObjectTable',
        'objectCategoryTable' => 'MonarcCore\Model\Table\ObjectCategoryTable',
        'objectObjectTable' => 'MonarcCore\Model\Table\ObjectObjectTable',
        'rolfRiskTable' => 'MonarcCore\Model\Table\RolfRiskTable',
        'rolfTagTable' => 'MonarcCore\Model\Table\RolfTagTable',
        'scaleTable' => 'MonarcCore\Model\Table\ScaleTable',
        'scaleCommentTable' => 'MonarcCore\Model\Table\ScaleCommentTable',
        'scaleImpactTypeTable' => 'MonarcCore\Model\Table\ScaleImpactTypeTable',
        'threatTable' => 'MonarcCore\Model\Table\ThreatTable',
        'themeTable' => 'MonarcCore\Model\Table\ThemeTable',
        'vulnerabilityTable' => 'MonarcCore\Model\Table\VulnerabilityTable',
        'questionTable' => 'MonarcCore\Model\Table\QuestionTable',
        'questionChoiceTable' => 'MonarcCore\Model\Table\QuestionChoiceTable',
        'SoaTable' => 'MonarcCore\Model\Table\SoaTable',

        //fo
        'anrCliTable' => 'MonarcFO\Model\Table\AnrTable',
        'amvCliTable' => 'MonarcFO\Model\Table\AmvTable',
        'anrObjectCategoryCliTable' => 'MonarcFO\Model\Table\AnrObjectCategoryTable',
        'assetCliTable' => 'MonarcFO\Model\Table\AssetTable',
        'instanceCliTable' => 'MonarcFO\Model\Table\InstanceTable',
        'instanceConsequenceCliTable' => 'MonarcFO\Model\Table\InstanceConsequenceTable',
        'instanceRiskCliTable' => 'MonarcFO\Model\Table\InstanceRiskTable',
        'instanceRiskOpCliTable' => 'MonarcFO\Model\Table\InstanceRiskOpTable',
        'interviewCliTable' => 'MonarcFO\Model\Table\InterviewTable',
        'measureCliTable' => 'MonarcFO\Model\Table\MeasureTable',
        'objectCliTable' => 'MonarcFO\Model\Table\ObjectTable',
        'objectCategoryCliTable' => 'MonarcFO\Model\Table\ObjectCategoryTable',
        'objectObjectCliTable' => 'MonarcFO\Model\Table\ObjectObjectTable',
        'recommandationCliTable' => 'MonarcFO\Model\Table\RecommandationTable',
        'recommandationHistoricCliTable' => 'MonarcFO\Model\Table\RecommandationHistoricTable',
        'recommandationMeasureCliTable' => 'MonarcFO\Model\Table\RecommandationMeasureTable',
        'recommandationRiskCliTable' => 'MonarcFO\Model\Table\RecommandationRiskTable',
        'rolfRiskCliTable' => 'MonarcFO\Model\Table\RolfRiskTable',
        'rolfTagCliTable' => 'MonarcFO\Model\Table\RolfTagTable',
        'scaleCliTable' => 'MonarcFO\Model\Table\ScaleTable',
        'scaleCommentCliTable' => 'MonarcFO\Model\Table\ScaleCommentTable',
        'scaleImpactTypeCliTable' => 'MonarcFO\Model\Table\ScaleImpactTypeTable',
        'snapshotCliTable' => 'MonarcFO\Model\Table\SnapshotTable',
        'threatCliTable' => 'MonarcFO\Model\Table\ThreatTable',
        'themeCliTable' => 'MonarcFO\Model\Table\ThemeTable',
        'userCliTable' => 'MonarcFO\Model\Table\UserTable',
        'userAnrCliTable' => 'MonarcFO\Model\Table\UserAnrTable',
        'userRoleTable' => 'MonarcFO\Model\Table\UserRoleTable',
        'vulnerabilityCliTable' => 'MonarcFO\Model\Table\VulnerabilityTable',
        'questionCliTable' => 'MonarcFO\Model\Table\QuestionTable',
        'questionChoiceCliTable' => 'MonarcFO\Model\Table\QuestionChoiceTable',
        'SoaCliTable' => 'MonarcFO\Model\Table\SoaTable',


        // export
        'instanceService' => '\MonarcFO\Service\AnrInstanceService',

    ];
}
