<?php
namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;

class DeliverableGenerationServiceFactory extends AbstractServiceFactory
{
    protected $ressources = array(
        'deliveryModelService' => '\MonarcCore\Service\DeliveriesModelsService'
    );
}
