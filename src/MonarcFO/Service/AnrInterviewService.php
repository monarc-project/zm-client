<?php
namespace MonarcFO\Service;

use MonarcCore\Service\AbstractService;
use Zend\View\Model\JsonModel;

/**
 * Anr Interview Service
 *
 * Class AnrInterviewService
 * @package MonarcCore\Service
 */
class AnrInterviewService extends AbstractService
{
    protected $anrTable;
    protected $userAnrTable;
    protected $dependencies = ['anr'];
}
