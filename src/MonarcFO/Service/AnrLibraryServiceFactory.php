<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2018 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
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
