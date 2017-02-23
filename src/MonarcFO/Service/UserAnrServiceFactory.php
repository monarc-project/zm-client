<?php
/**
 * @link      https://github.com/CASES-LU for the canonical source repository
 * @copyright Copyright (c) Cases is a registered trademark of SECURITYMADEIN.LU
 * @license   MyCases is licensed under the GNU Affero GPL v3 - See license.txt for more information
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