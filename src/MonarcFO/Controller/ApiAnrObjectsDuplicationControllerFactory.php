<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Controller;

use MonarcCore\Controller\AbstractControllerFactory;

/**
 * Api Anr Objects Duplication Controller Factory
 *
 * Class ApiAnrObjectsDuplicationControllerFactory
 * @package MonarcFO\Controller
 */
class ApiAnrObjectsDuplicationControllerFactory extends AbstractControllerFactory
{
    protected $serviceName = '\MonarcFO\Service\ObjectService';
}