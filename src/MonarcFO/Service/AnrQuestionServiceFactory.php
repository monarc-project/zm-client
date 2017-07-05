<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) Cases is a registered trademark of SECURITYMADEIN.LU
 * @license   MyCases is licensed under the GNU Affero GPL v3 - See license.txt for more information
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
