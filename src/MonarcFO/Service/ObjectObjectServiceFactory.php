<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) Cases is a registered trademark of SECURITYMADEIN.LU
 * @license   MyCases is licensed under the GNU Affero GPL v3 - See license.txt for more information
 */

namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;

/**
 * Proxy class to instantiate MonarcCore's ObjectObjectService, with MonarcFO's services
 * @package MonarcFO\Service
 */
class ObjectObjectServiceFactory extends AbstractServiceFactory
{
    protected $class = "\\MonarcCore\\Service\\ObjectObjectService";

    protected $ressources = [
        'table' => '\MonarcFO\Model\Table\ObjectObjectTable',
        'entity' => '\MonarcFO\Model\Entity\ObjectObject',
        'anrTable' => '\MonarcFO\Model\Table\AnrTable',
        'userAnrTable' => '\MonarcFO\Model\Table\UserAnrTable',
        'instanceTable' => '\MonarcFO\Model\Table\InstanceTable',
        'objectTable' => '\MonarcFO\Model\Table\ObjectTable',
        'childTable' => '\MonarcFO\Model\Table\ObjectTable',
        'fatherTable' => '\MonarcFO\Model\Table\ObjectTable',
    ];
}
