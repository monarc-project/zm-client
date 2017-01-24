<?php
namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;

/**
 * Anr Theme Service Factory
 *
 * Class AnrThemeServiceFactory
 * @package MonarcFO\Service
 */
class AnrThemeServiceFactory extends AbstractServiceFactory
{
    /**
     * @var array
     */
    protected $ressources = array(
        'entity'=> 'MonarcFO\Model\Entity\Theme',
        'table'=> 'MonarcFO\Model\Table\ThemeTable',
        'anrTable' => 'MonarcFO\Model\Table\AnrTable',
        'userAnrTable' => 'MonarcFO\Model\Table\UserAnrTable',
    );
}
