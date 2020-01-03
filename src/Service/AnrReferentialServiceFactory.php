<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Service\AbstractServiceFactory;

/**
 * Referential Service Factory
 *
 * Class AnrReferentialServiceFactory
 * @package Monarc\FrontOffice\Service
 */
class AnrReferentialServiceFactory extends AbstractServiceFactory
{
    protected $ressources = [
        'table' => 'Monarc\FrontOffice\Model\Table\ReferentialTable',
        'entity' => 'Monarc\FrontOffice\Model\Entity\Referential',
        'userAnrTable' => 'Monarc\FrontOffice\Model\Table\UserAnrTable',
        'selfCoreService' => 'Monarc\Core\Service\ReferentialService',
    ];
}
