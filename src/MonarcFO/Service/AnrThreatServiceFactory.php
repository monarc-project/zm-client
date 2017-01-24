<?php
namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;

/**
 * Class AnrThreatServiceFactory
 * @package MonarcFO\Service
 */
class AnrThreatServiceFactory extends AbstractServiceFactory
{
    /**
     * @var array
     */
    protected $ressources = array(
        'entity'=> 'MonarcFO\Model\Entity\Threat',
        'table'=> 'MonarcFO\Model\Table\ThreatTable',
        'anrTable' => 'MonarcFO\Model\Table\AnrTable',
        'userAnrTable' => 'MonarcFO\Model\Table\UserAnrTable',
        'userAnrTable' => 'MonarcFO\Model\Table\UserAnrTable',
        'themeTable' => 'MonarcFO\Model\Table\ThemeTable',
        'instanceRiskTable' => 'MonarcFO\Model\Table\InstanceRiskTable',
    );
}
