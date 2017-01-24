<?php
namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;
use Zend\ServiceManager\ServiceLocatorInterface;

/**
 * Anr Question Service Factory
 *
 * Class AnrQuestionServiceFactory
 * @package MonarcFO\Service
 */
class AnrQuestionServiceFactory extends AbstractServiceFactory
{
    protected $class = "\\MonarcCore\\Service\\QuestionService";

    protected $ressources = array(
        'table' => 'MonarcFO\Model\Table\QuestionTable',
        'entity' => 'MonarcFO\Model\Entity\Question',
        'choiceTable' => 'MonarcFO\Model\Table\QuestionChoiceTable',
        'anrTable' => 'MonarcFO\Model\Table\AnrTable',
        'userAnrTable' => 'MonarcFO\Model\Table\UserAnrTable',
    );
}
