<?php
namespace MonarcFO\Service\Model\Entity;

use MonarcCore\Service\Model\Entity\AbstractServiceModelEntity;

/**
 * Theme Service Model Entity
 *
 * Class ThemeServiceModelEntity
 * @package MonarcFO\Service\Model\Entity
 */
class ThemeServiceModelEntity extends AbstractServiceModelEntity
{
    protected $ressources = [
        'setDbAdapter' => '\MonarcCli\Model\Db',
    ];
}
