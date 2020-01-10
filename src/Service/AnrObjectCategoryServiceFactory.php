<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Service\AbstractServiceFactory;
use Monarc\FrontOffice\Model\Entity\ObjectCategory;
use Monarc\FrontOffice\Model\Table;

/**
 * Proxy class to instantiate Monarc\Core's ObjectCategoryService, with Monarc\FrontOffice's services
 * @package Monarc\FrontOffice\Service
 */
class AnrObjectCategoryServiceFactory extends AbstractServiceFactory
{
    protected $class = AnrObjectCategoryService::class;

    protected $ressources = [
        'table' => Table\ObjectCategoryTable::class,
        'entity' => ObjectCategory::class,
        'userAnrTable' => Table\UserAnrTable::class,
        'anrObjectCategoryTable' => Table\AnrObjectCategoryTable::class,
        'monarcObjectTable' => Table\MonarcObjectTable::class,
        'anrTable' => Table\AnrTable::class,
    ];
}
