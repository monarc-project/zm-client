<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Service\AbstractServiceFactory;
use Monarc\FrontOffice\Table\AnrTable;
use Monarc\FrontOffice\Table\UserAnrTable;

/**
 * Proxy class to instantiate Monarc\Core's QuestionChoiceService, with Monarc\FrontOffice's services
 * @package Monarc\FrontOffice\Service
 */
class AnrQuestionChoiceServiceFactory extends AbstractServiceFactory
{
    protected $class = AnrQuestionChoiceService::class;

    protected $ressources = [
        'table' => 'Monarc\FrontOffice\Model\Table\QuestionChoiceTable',
        'entity' => 'Monarc\FrontOffice\Model\Entity\QuestionChoice',
        'anrTable' => AnrTable::class,
        'userAnrTable' => UserAnrTable::class,
        'questionTable' => 'Monarc\FrontOffice\Model\Table\QuestionTable',
    ];
}
