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
 * Record Personal Data Service Factory
 *
 * Class AnrRecordPersonalDataServiceFactory
 * @package Monarc\FrontOffice\Service
 */
class AnrRecordPersonalDataServiceFactory extends AbstractServiceFactory
{
    protected $ressources = [
        'table' => 'Monarc\FrontOffice\Model\Table\RecordPersonalDataTable',
        'entity' => 'Monarc\FrontOffice\Model\Entity\RecordPersonalData',
        'recordDataCategoryService' => 'Monarc\FrontOffice\Service\AnrRecordDataCategoryService',
        'userAnrTable' => UserAnrTable::class,
        'anrTable' => AnrTable::class,
    ];
}
