<?php
namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;

class DeliverableGenerationServiceFactory extends AbstractServiceFactory
{
    protected $ressources = array(
        'deliveryModelService'  => '\MonarcCore\Service\DeliveriesModelsService',
        'anrTable'              => '\MonarcFO\Model\Table\AnrTable',
        'scaleService'          => '\MonarcFO\Service\AnrScaleService',
        'scaleTypeService'      => '\MonarcFO\Service\AnrScaleTypeService',
        'scaleCommentService'   => '\MonarcFO\Service\AnrScaleCommentService',
    );
}
