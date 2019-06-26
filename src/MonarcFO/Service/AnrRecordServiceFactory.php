<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;

/**
 * Record Service Factory
 *
 * Class AnrRecordServiceFactory
 * @package MonarcFO\Service
 */
class AnrRecordServiceFactory extends AbstractServiceFactory
{
    protected $ressources = [
        'table' => 'MonarcFO\Model\Table\RecordTable',
        'entity' => 'MonarcFO\Model\Entity\Record',
        'recordActorService' => 'MonarcFO\Service\AnrRecordActorService',
        'recordProcessorService'  => 'MonarcFO\Service\AnrRecordProcessorService',
        'recordRecipientService'  => 'MonarcFO\Service\AnrRecordRecipientService',
        'recordPersonalDataService'  => 'MonarcFO\Service\AnrRecordPersonalDataService',
        'recordInternationalTransferService'  => 'MonarcFO\Service\AnrRecordInternationalTransferService',
        'userAnrTable' => 'MonarcFO\Model\Table\UserAnrTable',
        'anrTable' => 'MonarcFO\Model\Table\AnrTable',
        'actorTable' => 'MonarcFO\Model\Table\RecordActorTable',
        'processorTable' => 'MonarcFO\Model\Table\RecordProcessorTable',
        'recipientTable' => 'MonarcFO\Model\Table\RecordRecipientTable',
    ];
}
