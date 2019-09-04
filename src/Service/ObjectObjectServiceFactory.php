<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Service\AbstractServiceFactory;

/**
 * Proxy class to instantiate Monarc\Core's ObjectObjectService, with Monarc\FrontOffice's services
 * @package Monarc\FrontOffice\Service
 */
class ObjectObjectServiceFactory extends AbstractServiceFactory
{
    protected $class = "\\Monarc\Core\\Service\\ObjectObjectService";

    protected $ressources = [
        'table' => '\Monarc\FrontOffice\Model\Table\ObjectObjectTable',
        'entity' => '\Monarc\FrontOffice\Model\Entity\ObjectObject',
        'anrTable' => '\Monarc\FrontOffice\Model\Table\AnrTable',
        'userAnrTable' => '\Monarc\FrontOffice\Model\Table\UserAnrTable',
        'instanceTable' => '\Monarc\FrontOffice\Model\Table\InstanceTable',
        'MonarcObjectTable' => '\Monarc\FrontOffice\Model\Table\MonarcObjectTable',
        'childTable' => '\Monarc\FrontOffice\Model\Table\MonarcObjectTable',
        'fatherTable' => '\Monarc\FrontOffice\Model\Table\MonarcObjectTable',
    ];
}
