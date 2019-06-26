<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;

/**
 * Record Actor Service Factory
 *
 * Class AnrRecordActorServiceFactory
 * @package MonarcFO\Service
 */
class AnrRecordActorServiceFactory extends AbstractServiceFactory
{
    protected $ressources = [
        'table' => 'MonarcFO\Model\Table\RecordActorTable',
        'entity' => 'MonarcFO\Model\Entity\RecordActor',
        'userAnrTable' => 'MonarcFO\Model\Table\UserAnrTable',
        'recordTable' => 'MonarcFO\Model\Table\RecordTable',
        'processorTable' => 'MonarcFO\Model\Table\RecordProcessorTable',
    ];
}
