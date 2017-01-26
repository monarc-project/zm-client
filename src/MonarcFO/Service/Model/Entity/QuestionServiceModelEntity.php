<?php
namespace MonarcFO\Service\Model\Entity;

use MonarcCore\Service\Model\Entity\AbstractServiceModelEntity;

/**
 * Question Service Model Entity
 *
 * Class QuestionServiceModelEntity
 * @package MonarcFO\Service\Model\Entity
 */
class QuestionServiceModelEntity extends AbstractServiceModelEntity
{
    protected $ressources = [
        'setDbAdapter' => '\MonarcCli\Model\Db',
    ];
}
