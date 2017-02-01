<?php
/**
 * @link      https://github.com/CASES-LU for the canonical source repository
 * @copyright Copyright (c) Cases is a registered trademark of SECURITYMADEIN.LU
 * @license   MyCases is licensed under the GNU Affero GPL v3 - See license.txt for more information
 */

namespace MonarcFO\Controller;

/**
 * Api Anr Questions Controller
 *
 * Class ApiAnrQuestionsController
 * @package MonarcFO\Controller
 */
class ApiAnrQuestionsController extends ApiAnrAbstractController
{
    protected $name = 'questions';

    protected $dependencies = ['anr'];
}