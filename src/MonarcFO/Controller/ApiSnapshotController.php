<?php
namespace MonarcFO\Controller;

use Zend\View\Model\JsonModel;

class ApiSnapshotController extends \MonarcCore\Controller\AbstractController
{
    protected $name = 'snapshots';

    protected $dependencies = ['anr'];

}

