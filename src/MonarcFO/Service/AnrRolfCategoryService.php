<?php
namespace MonarcFO\Service;

/**
 * Anr Rolf Category Service
 *
 * Class AnrRolfCategoryService
 * @package MonarcFO\Service
 */
class AnrRolfCategoryService extends \MonarcCore\Service\AbstractService
{
	protected $anrTable;
	protected $userAnrTable;

	protected $filterColumns = [
        'code', 'label1', 'label2', 'label3', 'label4',
    ];

    protected $dependencies = ['anr'];
}
