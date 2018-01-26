<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2018 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Controller;

use MonarcCore\Controller\AbstractController;

/**
 * Api Guides Controller
 *
 * Class ApiGuidesController
 * @package MonarcFO\Controller
 */
class ApiGuidesController extends AbstractController
{
    protected $name = 'guides';

    protected $dependencies = ['anr'];
}