<?php
namespace MonarcFO\Service\Model\Entity;

use MonarcCore\Service\Model\Entity\AbstractServiceModelEntity;

class QuestionChoiceServiceModelEntity extends AbstractServiceModelEntity
{
	protected $ressources = [
    	'setDbAdapter' => '\MonarcCli\Model\Db',
    ];
}
