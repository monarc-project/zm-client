<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;

/**
 * Record International Transfer Service Factory
 *
 * Class AnrRecordInternationalTransferServiceFactory
 * @package MonarcFO\Service
 */
class AnrRecordInternationalTransferServiceFactory extends AbstractServiceFactory
{
    protected $ressources = [
        'table' => 'MonarcFO\Model\Table\RecordInternationalTransferTable',
        'entity' => 'MonarcFO\Model\Entity\RecordInternationalTransfer',
        'userAnrTable' => 'MonarcFO\Model\Table\UserAnrTable',
        'anrTable' => 'MonarcFO\Model\Table\AnrTable',
    ];
}
