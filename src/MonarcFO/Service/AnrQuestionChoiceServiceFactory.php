<?php
namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;

/**
 * Anr Question Choice Service Factory
 *
 * Class AnrQuestionChoiceServiceFactory
 * @package MonarcFO\Service
 */
class AnrQuestionChoiceServiceFactory extends AbstractServiceFactory
{
    protected $class = "\\MonarcCore\\Service\\QuestionChoiceService";

    protected $ressources = [
        'table' => 'MonarcFO\Model\Table\QuestionChoiceTable',
        'entity' => 'MonarcFO\Model\Entity\QuestionChoice',
        'anrTable' => 'MonarcFO\Model\Table\AnrTable',
        'userAnrTable' => 'MonarcFO\Model\Table\UserAnrTable',
        'questionTable' => 'MonarcFO\Model\Table\QuestionTable',
    ];
}
