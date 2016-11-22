<?php
namespace MonarcFO\Service;

use MonarcCore\Service\AbstractService;

class UserAnrService extends AbstractService
{
    protected $anrTable;
    protected $userTable;

    protected $dependencies = ['anr', 'user'];


}