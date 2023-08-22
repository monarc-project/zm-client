<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Service\AbstractServiceFactory;
use Monarc\FrontOffice\Table\UserAnrTable;

/**
 * Record Actor Service Factory
 *
 * Class AnrRecordActorServiceFactory
 * @package Monarc\FrontOffice\Service
 */
class AnrRecordActorServiceFactory extends AbstractServiceFactory
{
    protected $ressources = [
        'table' => 'Monarc\FrontOffice\Model\Table\RecordActorTable',
        'entity' => 'Monarc\FrontOffice\Model\Entity\RecordActor',
        'userAnrTable' => UserAnrTable::class,
        'recordTable' => 'Monarc\FrontOffice\Model\Table\RecordTable',
        'processorTable' => 'Monarc\FrontOffice\Model\Table\RecordProcessorTable',
    ];
}
