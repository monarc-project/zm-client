<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
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
        'MonarcObjectTable' => '\MonarcFO\Model\Table\MonarcObjectTable',
        'childTable' => '\MonarcFO\Model\Table\MonarcObjectTable',
        'fatherTable' => '\MonarcFO\Model\Table\MonarcObjectTable',
    ];
}
