<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Service\AbstractServiceFactory;

/**
 * Proxy class to instantiate Monarc\Core's AnrObjectService, in order to provide the common library to a client ANR
 * @package Monarc\FrontOffice\Service
 */
class AnrLibraryServiceFactory extends AbstractServiceFactory
{
    protected $class = AnrLibraryService::class;

    protected $ressources = [
        'table' => 'Monarc\FrontOffice\Model\Table\MonarcObjectTable',
        'entity' => 'Monarc\FrontOffice\Model\Entity\MonarcObject',
        'objectObjectTable' => 'Monarc\FrontOffice\Model\Table\ObjectObjectTable',
        'objectService' => 'Monarc\FrontOffice\Service\ObjectService',
        'userAnrTable' => 'Monarc\FrontOffice\Model\Table\UserAnrTable',
    ];
}
