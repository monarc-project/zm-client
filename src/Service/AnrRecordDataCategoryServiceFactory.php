<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Service\AbstractServiceFactory;
use Monarc\FrontOffice\Table\UserAnrTable;

/**
 * Record Data Category Service Factory
 *
 * Class AnrRecordDataCategoryServiceFactory
 * @package Monarc\FrontOffice\Service
 */
class AnrRecordDataCategoryServiceFactory extends AbstractServiceFactory
{
    protected $ressources = [
        'table' => 'Monarc\FrontOffice\Model\Table\RecordDataCategoryTable',
        'entity' => 'Monarc\FrontOffice\Entity\RecordDataCategory',
        'userAnrTable' => UserAnrTable::class,
        'personalDataTable' => 'Monarc\FrontOffice\Model\Table\RecordPersonalDataTable',
    ];
}
