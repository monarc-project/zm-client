<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Service\AbstractServiceFactory;

/**
 * Factory class attached to UserAnrService
 * @package Monarc\FrontOffice\Service
 */
class UserAnrServiceFactory extends AbstractServiceFactory
{
    protected $ressources = [
        'table' => '\Monarc\FrontOffice\Model\Table\UserAnrTable',
        'entity' => '\Monarc\FrontOffice\Model\Entity\UserAnr',
        'anrTable' => '\Monarc\FrontOffice\Model\Table\AnrTable',
        'userTable' => '\Monarc\FrontOffice\Model\Table\UserTable',
    ];
}
