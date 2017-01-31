<?php
/**
 * @link      https://github.com/CASES-LU for the canonical source repository
 * @copyright Copyright (c) Cases is a registered trademark of SECURITYMADEIN.LU
 * @license   MyCases is licensed under the GNU Affero GPL v3 - See license.txt for more information
 */

namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;

/**
 * Client Service Factory
 *
 * Class ClientServiceFactory
 * @package MonarcFO\Service
 */
class ClientServiceFactory extends AbstractServiceFactory
{
    protected $ressources = [
        'table' => '\MonarcFO\Model\Table\ClientTable',
        'entity' => '\MonarcFO\Model\Entity\Client',
        'countryTable' => '\MonarcCore\Model\Table\CountryTable',
        'countryEntity' => '\MonarcCore\Model\Entity\Country',
        'cityTable' => '\MonarcCore\Model\Table\CityTable',
        'cityEntity' => '\MonarcCore\Model\Entity\City',
    ];
}