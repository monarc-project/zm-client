<?php

namespace MonarcFO\Controller;

use Zend\View\Model\JsonModel;

/**
 * Api ANR Amvs Controller
 *
 * Class ApiAnrAmvsController
 * @package MonarcFO\Controller
 */
class ApiAnrAmvsController extends ApiAnrAbstractController
{
    protected $name = 'amvs';
    protected $dependencies = ['asset', 'threat', 'vulnerability', 'measure1', 'measure2', 'measure3'];
}
