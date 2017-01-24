<?php
namespace MonarcFO\Service;

use \MonarcCore\Service\AbstractServiceFactory;
use \Zend\ServiceManager\ServiceLocatorInterface;

/**
 * Anr Instance Service Factory
 *
 * Class AnrInstanceServiceFactory
 * @package MonarcFO\Service
 */
class AnrInstanceServiceFactory extends AbstractServiceFactory
{
    /**
     * @var array
     */
    protected $ressources = array(
        // Tables & Entities
        'table' => 'MonarcFO\Model\Table\InstanceTable',
        'entity' => 'MonarcFO\Model\Entity\Instance',
        'anrTable' => 'MonarcFO\Model\Table\AnrTable',
        'userAnrTable' => 'MonarcFO\Model\Table\UserAnrTable',
        'amvTable' => 'MonarcFO\Model\Table\AmvTable',
        'objectTable' => 'MonarcFO\Model\Table\ObjectTable',
        'scaleTable' => 'MonarcFO\Model\Table\ScaleTable',
        'scaleImpactTypeTable' => 'MonarcFO\Model\Table\ScaleImpactTypeTable',
        'instanceConsequenceTable' => 'MonarcFO\Model\Table\InstanceConsequenceTable',
        'instanceConsequenceEntity' => 'MonarcFO\Model\Entity\InstanceConsequence',
        'recommandationRiskTable' => 'MonarcFO\Model\Table\RecommandationRiskTable',
        'recommandationMeasureTable' => 'MonarcFO\Model\Table\RecommandationMeasureTable',
        'recommandationTable' => 'MonarcFO\Model\Table\RecommandationTable',

        // Services
        'instanceConsequenceService' => 'MonarcFO\Service\AnrInstanceConsequenceService',
        'instanceRiskService' => 'MonarcFO\Service\AnrInstanceRiskService',
        'instanceRiskOpService' => 'MonarcFO\Service\AnrInstanceRiskOpService',
        'objectObjectService' => 'MonarcFO\Service\ObjectObjectService',
        'translateService' => 'MonarcCore\Service\TranslateService',

        // Useless (Deprecated)
        'assetTable' => 'MonarcFO\Model\Table\AssetTable',
        'instanceTable' => 'MonarcFO\Model\Table\InstanceTable',
        'rolfRiskTable' => 'MonarcFO\Model\Table\RolfRiskTable',

        // Export (Services)
        'objectExportService' => 'MonarcFO\Service\ObjectExportService',
        'amvService' => 'MonarcFO\Service\AmvService',
    );
}
