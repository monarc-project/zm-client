<?php
namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;

class AnrServiceFactory extends AbstractServiceFactory
{
    protected $ressources = array(

        //core
        'entity'    => 'MonarcCore\Model\Entity\Anr',
        'table'     => 'MonarcCore\Model\Table\AnrTable',
        'amvTable'  => 'MonarcCore\Model\Table\AmvTable',
        'anrObjectCategoryTable'   => 'MonarcCore\Model\Table\AnrObjectCategoryTable',
        'assetTable' => 'MonarcCore\Model\Table\AssetTable',
        'measureTable' => 'MonarcCore\Model\Table\MeasureTable',
        'modelTable' => 'MonarcCore\Model\Table\ModelTable',
        'objectTable'   => 'MonarcCore\Model\Table\ObjectTable',
        'objectCategoryTable'   => 'MonarcCore\Model\Table\ObjectCategoryTable',
        'objectObjectTable'   => 'MonarcCore\Model\Table\ObjectObjectTable',
        'rolfCategoryTable' => 'MonarcCore\Model\Table\RolfCategoryTable',
        'rolfRiskTable' => 'MonarcCore\Model\Table\RolfRiskTable',
        'rolfTagTable' => 'MonarcCore\Model\Table\RolfTagTable',
        'threatTable' => 'MonarcCore\Model\Table\ThreatTable',
        'vulnerabilityTable' => 'MonarcCore\Model\Table\VulnerabilityTable',

        //fo
        'cliEntity'=> 'MonarcFO\Model\Entity\Anr',
        'cliTable'=> 'MonarcFO\Model\Table\AnrTable',
        'amvCliTable' => 'MonarcFO\Model\Table\AmvTable',
        'anrObjectCategoryCliTable' => 'MonarcFO\Model\Table\AnrObjectCategoryTable',
        'assetCliTable' => 'MonarcFO\Model\Table\AssetTable',
        'measureCliTable' => 'MonarcFO\Model\Table\MeasureTable',
        'objectCliTable' => 'MonarcFO\Model\Table\ObjectTable',
        'objectCategoryCliTable' => 'MonarcFO\Model\Table\ObjectCategoryTable',
        'objectObjectCliTable' => 'MonarcFO\Model\Table\ObjectObjectTable',
        'rolfCategoryCliTable' => 'MonarcFO\Model\Table\RolfCategoryTable',
        'rolfRiskCliTable' => 'MonarcFO\Model\Table\RolfRiskTable',
        'rolfTagCliTable' => 'MonarcFO\Model\Table\RolfTagTable',
        'threatCliTable' => 'MonarcFO\Model\Table\ThreatTable',
        'vulnerabilityCliTable' => 'MonarcFO\Model\Table\VulnerabilityTable',
    );
}
