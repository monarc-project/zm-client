<?php
namespace MonarcFO\Service\Model\Entity;

use MonarcCore\Service\Model\Entity\AbstractServiceModelEntity;

/**
 * Interview Service Model Entity
 *
 * Class InterviewServiceModelEntity
 * @package MonarcFO\Service\Model\Entity
 */
class InterviewServiceModelEntity extends AbstractServiceModelEntity
{
    protected $ressources = [
        'setDbAdapter' => '\MonarcCli\Model\Db',
    ];
}
