<?php
namespace MonarcFO\Service;

/**
 * Anr Measure Service
 *
 * Class AnrMeasureService
 * @package MonarcFO\Service
 */
class AnrMeasureService extends \MonarcCore\Service\AbstractService
{
	protected $filterColumns = array(
        'description1', 'description2', 'description3', 'description4',
        'code', 'status'
    );

    protected $anrTable;
    protected $dependencies = ['anr'];
}
