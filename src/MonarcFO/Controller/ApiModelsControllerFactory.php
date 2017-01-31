<?php
/**
 * @link      https://github.com/CASES-LU for the canonical source repository
 * @copyright Copyright (c) Cases is a registered trademark of SECURITYMADEIN.LU
 * @license   MyCases is licensed under the GNU Affero GPL v3 - See license.txt for more information
 */

namespace MonarcFO\Controller;

use MonarcCore\Controller\AbstractControllerFactory;

/**
 * Api Models Controller Factory
 *
 * Class ApiModelsControllerFactory
 * @package MonarcFO\Controller
 */
class ApiModelsControllerFactory extends AbstractControllerFactory
{
    protected $serviceName = '\MonarcFO\Service\ModelService';
}