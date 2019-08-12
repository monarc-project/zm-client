<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;

/**
 * Record Personal Data Service Factory
 *
 * Class AnrRecordPersonalDataServiceFactory
 * @package MonarcFO\Service
 */
class AnrRecordPersonalDataServiceFactory extends AbstractServiceFactory
{
    protected $ressources = [
        'table' => 'MonarcFO\Model\Table\RecordPersonalDataTable',
        'entity' => 'MonarcFO\Model\Entity\RecordPersonalData',
        'recordDataCategoryService' => 'MonarcFO\Service\AnrRecordDataCategoryService',
        'userAnrTable' => 'MonarcFO\Model\Table\UserAnrTable',
        'anrTable' => 'MonarcFO\Model\Table\AnrTable',
    ];
}
