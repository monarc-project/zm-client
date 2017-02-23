<?php
/**
 * @link      https://github.com/CASES-LU for the canonical source repository
 * @copyright Copyright (c) Cases is a registered trademark of SECURITYMADEIN.LU
 * @license   MyCases is licensed under the GNU Affero GPL v3 - See license.txt for more information
 */

namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;

/**
 * Proxy class to instantiate MonarcCore's QuestionChoiceService, with MonarcFO's services
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
