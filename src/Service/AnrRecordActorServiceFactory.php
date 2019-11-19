<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Service\AbstractServiceFactory;

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
        'userAnrTable' => 'Monarc\FrontOffice\Model\Table\UserAnrTable',
        'recordTable' => 'Monarc\FrontOffice\Model\Table\RecordTable',
        'processorTable' => 'Monarc\FrontOffice\Model\Table\RecordProcessorTable',
    ];
}
