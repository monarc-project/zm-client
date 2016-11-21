<?php
namespace MonarcFO\Service;

/**
 * Anr Asset Service
 *
 * Class AnrAssetService
 * @package MonarcFO\Service
 */
class AnrAssetService extends \MonarcCore\Service\AbstractService
{
	protected $anrTable;

	protected $filterColumns = [
        'label1', 'label2', 'label3', 'label4',
        'description1', 'description2', 'description3', 'description4',
        'code',
    ];

    protected $dependencies = ['anr'];
}
