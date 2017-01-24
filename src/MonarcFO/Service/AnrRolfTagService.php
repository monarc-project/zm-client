<?php
namespace MonarcFO\Service;

/**
 * Anr Rolf Tag Service
 *
 * Class AnrRolfTagService
 * @package MonarcFO\Service
 */
class AnrRolfTagService extends \MonarcCore\Service\AbstractService
{
    protected $anrTable;
    protected $userAnrTable;
    protected $filterColumns = ['code', 'label1', 'label2', 'label3', 'label4'];
    protected $dependencies = ['anr'];
}
