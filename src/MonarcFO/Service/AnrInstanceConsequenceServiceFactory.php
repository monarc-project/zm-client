<?php
namespace MonarcFO\Service;

use \MonarcCore\Service\AbstractServiceFactory;

/**
 * Anr Instance Consequence Service Factory
 *
 * Class AnrInstanceConsequenceServiceFactory
 * @package MonarcFO\Service
 */
class AnrInstanceConsequenceServiceFactory extends AbstractServiceFactory
{
    protected $class = "\\MonarcCore\\Service\\InstanceConsequenceService";

    protected $ressources = [
        'table' => 'MonarcFO\Model\Table\InstanceConsequenceTable',
        'entity' => 'MonarcFO\Model\Entity\InstanceConsequence',
        'anrTable' => 'MonarcFO\Model\Table\AnrTable',
        'instanceTable' => 'MonarcFO\Model\Table\InstanceTable',
        'objectTable' => 'MonarcFO\Model\Table\ObjectTable',
        'scaleTable' => 'MonarcFO\Model\Table\ScaleTable',
        'scaleImpactTypeTable' => 'MonarcFO\Model\Table\ScaleImpactTypeTable',
    ];
}
