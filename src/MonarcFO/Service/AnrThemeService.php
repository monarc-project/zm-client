<?php
namespace MonarcFO\Service;

/**
 * Anr Theme Service
 *
 * Class AnrThemeService
 * @package MonarcFO\Service
 */
class AnrThemeService extends \MonarcCore\Service\AbstractService
{
    protected $filterColumns = ['label1', 'label2', 'label3', 'label4'];
    protected $dependencies = ['anr'];
    protected $anrTable;
    protected $userAnrTable;
}
