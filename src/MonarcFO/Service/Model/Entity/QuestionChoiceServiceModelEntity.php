<?php
namespace MonarcFO\Service\Model\Entity;

use MonarcCore\Service\Model\Entity\AbstractServiceModelEntity;

/**
 * Question Choice Service Model Entity
 *
 * Class QuestionChoiceServiceModelEntity
 * @package MonarcFO\Service\Model\Entity
 */
class QuestionChoiceServiceModelEntity extends AbstractServiceModelEntity
{
    protected $ressources = [
        'setDbAdapter' => '\MonarcCli\Model\Db',
    ];
}
