<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Service\AbstractServiceFactory;

/**
 * Proxy class to instantiate Monarc\Core's ObjectCategoryService, with Monarc\FrontOffice's services
 * @package Monarc\FrontOffice\Service
 */
class ObjectCategoryServiceFactory extends AbstractServiceFactory
{
    protected $class = "\\Monarc\Core\\Service\\ObjectCategoryService";

    protected $ressources = [
        'table' => 'Monarc\FrontOffice\Model\Table\ObjectCategoryTable',
        'entity' => 'Monarc\FrontOffice\Model\Entity\ObjectCategory',
        'anrObjectCategoryTable' => 'Monarc\FrontOffice\Model\Table\AnrObjectCategoryTable',
        'MonarcObjectTable' => 'Monarc\FrontOffice\Model\Table\MonarcObjectTable',
        'rootTable' => 'Monarc\FrontOffice\Model\Table\ObjectCategoryTable',
        'parentTable' => 'Monarc\FrontOffice\Model\Table\ObjectCategoryTable',
        'anrTable' => 'Monarc\FrontOffice\Model\Table\AnrTable',
    ];
}
