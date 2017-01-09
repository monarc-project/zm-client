<?php
namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;

class ClientServiceFactory extends AbstractServiceFactory
{
    protected $ressources = array(
        'table'=> '\MonarcFO\Model\Table\ClientTable',
        'entity'=> '\MonarcFO\Model\Entity\Client',
        'countryTable'=> '\MonarcCore\Model\Table\CountryTable',
        'countryEntity'=> '\MonarcCore\Model\Entity\Country',
        'cityTable'=> '\MonarcCore\Model\Table\CityTable',
        'cityEntity'=> '\MonarcCore\Model\Entity\City',
    );
}
