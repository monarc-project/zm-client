<?php
namespace MonarcFO\Service;

/**
 * Anr Rolf Risk Service
 *
 * Class AnrRolfRiskService
 * @package MonarcFO\Service
 */
class AnrRolfRiskService extends \MonarcCore\Service\RolfRiskService 
{
	protected $filterColumns = [
        'code', 'label1', 'label2', 'label3', 'label4', 'description1', 'description2', 'description3', 'description4'
    ];
    protected $dependencies = ['anr', 'categor[ies](y)', 'tag[s]()'];

    protected $anrTable;
    protected $categoryTable;
    protected $tagTable;
}
