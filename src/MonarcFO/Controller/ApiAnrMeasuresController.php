<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2018 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Controller;
use Zend\View\Model\JsonModel;

/**
 * Api ANR Measures Controller
 *
 * Class ApiAnrMeasuresController
 * @package MonarcFO\Controller
 */
class ApiAnrMeasuresController extends ApiAnrAbstractController
{
    protected $name = 'measures';
    protected $dependencies = ['anr', 'category'];

    // protected $dependencies = ['category', 'anr'];







}
