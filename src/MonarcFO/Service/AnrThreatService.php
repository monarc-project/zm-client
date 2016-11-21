<?php
namespace MonarcFO\Service;

/**
 * Anr Threat Service
 *
 * Class AnrThreatService
 * @package MonarcFO\Service
 */
class AnrThreatService extends \MonarcCore\Service\AbstractService
{
	protected $filterColumns = [
        'label1', 'label2', 'label3', 'label4',
        'description1', 'description2', 'description3', 'description4',
        'code',
    ];
    protected $dependencies = ['anr', 'theme'];
}
