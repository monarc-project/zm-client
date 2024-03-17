<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Service\AbstractServiceFactory;
use Monarc\FrontOffice\Table\UserAnrTable;
use Monarc\FrontOffice\Table\AnrTable;

/**
 * Record Recipient Service Factory
 *
 * Class AnrRecordRecipientServiceFactory
 * @package Monarc\FrontOffice\Service
 */
class AnrRecordRecipientServiceFactory extends AbstractServiceFactory
{
    protected $ressources = [
        'table' => 'Monarc\FrontOffice\Model\Table\RecordRecipientTable',
        'entity' => 'Monarc\FrontOffice\Entity\RecordRecipient',
        'recordTable' => 'Monarc\FrontOffice\Model\Table\RecordTable',
        'userAnrTable' => UserAnrTable::class,
        'anrTable' => AnrTable::class,
    ];
}
