<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;

/**
 * Record Data Category Service Factory
 *
 * Class AnrRecordDataCategoryServiceFactory
 * @package MonarcFO\Service
 */
class AnrRecordDataCategoryServiceFactory extends AbstractServiceFactory
{
    protected $ressources = [
        'table' => 'MonarcFO\Model\Table\RecordDataCategoryTable',
        'entity' => 'MonarcFO\Model\Entity\RecordDataCategory',
        'userAnrTable' => 'MonarcFO\Model\Table\UserAnrTable',
        'personalDataTable' => 'MonarcFO\Model\Table\RecordPersonalDataTable',
    ];
}
