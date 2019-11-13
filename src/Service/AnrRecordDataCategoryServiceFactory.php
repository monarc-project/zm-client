<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Service\AbstractServiceFactory;

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
        'entity' => 'Monarc\FrontOffice\Model\Entity\RecordDataCategory',
        'userAnrTable' => 'Monarc\FrontOffice\Model\Table\UserAnrTable',
        'personalDataTable' => 'Monarc\FrontOffice\Model\Table\RecordPersonalDataTable',
    ];
}
