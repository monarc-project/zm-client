<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\Core\Controller\AbstractControllerFactory;

/**
 * Api Guides Controller Factory
 *
 * Class ApiGuidesControllerFactory
 * @package Monarc\FrontOffice\Controller
 */
class ApiGuidesControllerFactory extends AbstractControllerFactory
{
    protected $serviceName = 'Monarc\Core\Service\GuideService';
}