<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;

/**
 * Record Recipient Category Service Factory
 *
 * Class AnrRecordRecipientCategoryServiceFactory
 * @package MonarcFO\Service
 */
class AnrRecordRecipientCategoryServiceFactory extends AbstractServiceFactory
{
    protected $ressources = [
        'table' => 'MonarcFO\Model\Table\RecordRecipientCategoryTable',
        'entity' => 'MonarcFO\Model\Entity\RecordRecipientCategory',
        'userAnrTable' => 'MonarcFO\Model\Table\UserAnrTable',
    ];
}
