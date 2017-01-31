<?php
/**
 * @link      https://github.com/CASES-LU for the canonical source repository
 * @copyright Copyright (c) Cases is a registered trademark of SECURITYMADEIN.LU
 * @license   MyCases is licensed under the GNU Affero GPL v3 - See license.txt for more information
 */

namespace MonarcFO\Controller;

/**
 * Api ANR Threats Controller
 *
 * Class ApiAnrThreatsController
 * @package MonarcFO\Controller
 */
class ApiAnrThreatsController extends ApiAnrAbstractController
{
    protected $name = 'threats';

    protected $dependencies = ['anr', 'theme'];
}