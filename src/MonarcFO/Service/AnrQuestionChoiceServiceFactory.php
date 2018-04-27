<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2018 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
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
