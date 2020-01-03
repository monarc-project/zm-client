<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\Core\Controller\AbstractController;

/**
 * Api Guides Controller
 *
 * Class ApiGuidesController
 * @package Monarc\FrontOffice\Controller
 */
class ApiGuidesController extends AbstractController
{
    protected $name = 'guides';

    protected $dependencies = ['anr'];
}
