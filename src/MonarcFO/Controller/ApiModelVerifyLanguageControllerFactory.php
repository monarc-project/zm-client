<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2018 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Controller;

use MonarcCore\Controller\AbstractControllerFactory;

/**
 * Api Verify Language Controller Factory
 *
 * Class ApiModelVerifyLanguageControllerFactory
 * @package MonarcFO\Controller
 */
class ApiModelVerifyLanguageControllerFactory extends AbstractControllerFactory
{
    protected $serviceName = '\MonarcFO\Service\AnrService';
}