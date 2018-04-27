<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2018 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;

/**
 * Proxy class to instantiate MonarcCore's QuestionService, with MonarcFO's services
 * @package MonarcFO\Service
 */
class AnrQuestionServiceFactory extends AbstractServiceFactory
{
    protected $class = "\\MonarcCore\\Service\\QuestionService";

    protected $ressources = [
        'table' => 'MonarcFO\Model\Table\QuestionTable',
        'entity' => 'MonarcFO\Model\Entity\Question',
        'choiceTable' => 'MonarcFO\Model\Table\QuestionChoiceTable',
        'anrTable' => 'MonarcFO\Model\Table\AnrTable',
        'userAnrTable' => 'MonarcFO\Model\Table\UserAnrTable',
    ];
}
