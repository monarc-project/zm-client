<?php
/**
 * @link      https://github.com/CASES-LU for the canonical source repository
 * @copyright Copyright (c) Cases is a registered trademark of SECURITYMADEIN.LU
 * @license   MyCases is licensed under the GNU Affero GPL v3 - See license.txt for more information
 */

namespace MonarcFO\Controller;

use MonarcCore\Controller\AbstractControllerFactory;

/**
 * Api Config Controlelr Factory
 *
 * Class ApiConfigControllerFactory
 * @package MonarcFO\Controller
 */
class ApiConfigControllerFactory extends AbstractControllerFactory
{
    protected $serviceName = 'MonarcCore\Service\ConfigService';
}