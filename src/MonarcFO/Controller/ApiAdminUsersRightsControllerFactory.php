<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) Cases is a registered trademark of SECURITYMADEIN.LU
 * @license   MyCases is licensed under the GNU Affero GPL v3 - See license.txt for more information
 */

namespace MonarcFO\Controller;

/**
 * Factory class attached to ApiAdminUsersRightsController
 * @package MonarcFO\Controller
 */
class ApiAdminUsersRightsControllerFactory extends \MonarcCore\Controller\AbstractControllerFactory
{
    protected $serviceName = '\MonarcFO\Service\UserAnrService';
}

