<?php
namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;

/**
 * Model Service Factory
 *
 * Class ModelServiceFactory
 * @package MonarcFO\Service
 */
class ModelServiceFactory extends AbstractServiceFactory
{
    protected $class = "\\MonarcCore\\Service\\ModelService";

    protected $ressources = [
        'table' => 'MonarcCore\Model\Table\ModelTable',
        'entity' => 'MonarcCore\Model\Entity\Model',
        'anrService' => 'MonarcCore\Service\AnrService',
        'anrTable' => 'MonarcCore\Model\Table\AnrTable',
        'instanceRiskTable' => 'MonarcCore\Model\Table\InstanceRiskTable',
        'instanceRiskOpTable' => 'MonarcCore\Model\Table\InstanceRiskOpTable',
        'objectTable' => 'MonarcCore\Model\Table\ObjectTable',
        'amvTable' => 'MonarcCore\Model\Table\AmvTable',
        'clientTable' => '\MonarcFO\Model\Table\ClientTable'
    ];
}