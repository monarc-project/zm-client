<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Service\AbstractServiceFactory;

/**
 * Record Processor Service Factory
 *
 * Class AnrRecordProcessorServiceFactory
 * @package Monarc\FrontOffice\Service
 */
class AnrRecordProcessorServiceFactory extends AbstractServiceFactory
{
    protected $ressources = [
        'table' => 'Monarc\FrontOffice\Model\Table\RecordProcessorTable',
        'entity' => 'Monarc\FrontOffice\Model\Entity\RecordProcessor',
        'recordActorService' => 'Monarc\FrontOffice\Service\AnrRecordActorService',
        'userAnrTable' => 'Monarc\FrontOffice\Model\Table\UserAnrTable',
        'anrTable' => 'Monarc\FrontOffice\Model\Table\AnrTable',
        'recordTable' => 'Monarc\FrontOffice\Model\Table\RecordTable',
    ];
}
