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
 * Record Service Factory
 *
 * Class AnrRecordServiceFactory
 * @package Monarc\FrontOffice\Service
 */
class AnrRecordServiceFactory extends AbstractServiceFactory
{
    protected $ressources = [
        'table' => 'Monarc\FrontOffice\Model\Table\RecordTable',
        'entity' => 'Monarc\FrontOffice\Model\Entity\Record',
        'recordActorService' => 'Monarc\FrontOffice\Service\AnrRecordActorService',
        'recordProcessorService'  => 'Monarc\FrontOffice\Service\AnrRecordProcessorService',
        'recordRecipientService'  => 'Monarc\FrontOffice\Service\AnrRecordRecipientService',
        'recordPersonalDataService'  => 'Monarc\FrontOffice\Service\AnrRecordPersonalDataService',
        'recordInternationalTransferService'  => 'Monarc\FrontOffice\Service\AnrRecordInternationalTransferService',
        'userAnrTable' => UserAnrTable::class,
        'anrTable' => AnrTable::class,
        'actorTable' => 'Monarc\FrontOffice\Model\Table\RecordActorTable',
        'processorTable' => 'Monarc\FrontOffice\Model\Table\RecordProcessorTable',
        'recipientTable' => 'Monarc\FrontOffice\Model\Table\RecordRecipientTable',
        'personalDataTable' => 'Monarc\FrontOffice\Model\Table\RecordPersonalDataTable',
        'internationalTransferTable' => 'Monarc\FrontOffice\Model\Table\RecordInternationalTransferTable',
    ];
}
