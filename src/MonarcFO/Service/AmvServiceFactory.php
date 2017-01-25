<?php
namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;
use Zend\ServiceManager\ServiceLocatorInterface;

/**
 * Amv Service Factory
 *
 * Class AmvServiceFactory
 * @package MonarcFO\Service
 */
class AmvServiceFactory extends AbstractServiceFactory
{
    protected $class = "\\MonarcCore\\Service\\AmvService";

    protected $ressources = [
        'table' => 'MonarcFO\Model\Table\AmvTable',
        'entity' => 'MonarcFO\Model\Entity\Amv',
        'anrTable' => '\MonarcFO\Model\Table\AnrTable',
        'assetTable' => '\MonarcFO\Model\Table\AssetTable',
        'instanceTable' => 'MonarcCore\Model\Table\InstanceTable',
        'measureTable' => '\MonarcFO\Model\Table\MeasureTable',
        'threatTable' => '\MonarcFO\Model\Table\ThreatTable',
        'vulnerabilityTable' => '\MonarcFO\Model\Table\VulnerabilityTable',
    ];
}
