<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) Cases is a registered trademark of SECURITYMADEIN.LU
 * @license   MyCases is licensed under the GNU Affero GPL v3 - See license.txt for more information
 */

namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;

/**
 * Proxy class to instantiate MonarcCore's AnrObjectService, in order to provide the common library to a client ANR
 * @package MonarcFO\Service
 */
class AnrLibraryServiceFactory extends AbstractServiceFactory
{
    protected $class = "\\MonarcCore\\Service\\AnrObjectService";

    protected $ressources = [
        'table' => 'MonarcFO\Model\Table\ObjectTable',
        'entity' => 'MonarcFO\Model\Entity\Object',
        'objectObjectTable' => 'MonarcFO\Model\Table\ObjectObjectTable',
        'objectService' => 'MonarcFO\Service\ObjectService',
        'userAnrTable' => 'MonarcFO\Model\Table\UserAnrTable',
    ];
}
