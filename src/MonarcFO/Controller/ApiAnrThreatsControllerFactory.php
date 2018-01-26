<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2018 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Controller;

use MonarcCore\Controller\AbstractControllerFactory;

/**
 * Api Anr Threats Controller Factory
 *
 * Class ApiAnrThreatsControllerFactory
 * @package MonarcFO\Controller
 */
class ApiAnrThreatsControllerFactory extends AbstractControllerFactory
{
    protected $serviceName = '\MonarcFO\Service\AnrThreatService';
}