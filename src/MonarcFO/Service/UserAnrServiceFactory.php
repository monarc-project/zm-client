<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2018 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;

/**
 * Factory class attached to UserAnrService
 * @package MonarcFO\Service
 */
class UserAnrServiceFactory extends AbstractServiceFactory
{
    protected $ressources = [
        'table' => '\MonarcFO\Model\Table\UserAnrTable',
        'entity' => '\MonarcFO\Model\Entity\UserAnr',
        'anrTable' => '\MonarcFO\Model\Table\AnrTable',
        'userTable' => '\MonarcFO\Model\Table\UserTable',
    ];
}