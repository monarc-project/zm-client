<?php
namespace MonarcFO\Service;

/**
 * Anr Scale Comment Service
 *
 * Class AnrScaleCommentService
 * @package MonarcFO\Service
 */
class AnrScaleCommentService extends \MonarcCore\Service\AbstractService
{
	protected $filterColumns = array();

    protected $anrTable;
    protected $scaleTable;
    protected $scaleImpactTypeTable;
    protected $dependencies = ['anr', 'scale', 'scaleImpactType'];
}
