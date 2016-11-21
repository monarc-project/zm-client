<?php
namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;

class AnrThemeServiceFactory extends AbstractServiceFactory
{
    protected $ressources = array(
        'entity'=> 'MonarcFO\Model\Entity\Theme',
        'table'=> 'MonarcFO\Model\Table\ThemeTable',
        'anrTable' => 'MonarcFO\Model\Table\AnrTable',
    );
}
