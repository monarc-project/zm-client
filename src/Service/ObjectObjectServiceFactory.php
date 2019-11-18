<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Interop\Container\ContainerInterface;
use Monarc\Core\Service\AbstractServiceFactory;
use Monarc\Core\Service\ObjectObjectService;
use Monarc\FrontOffice\Model\Entity\ObjectObject;
use Monarc\FrontOffice\Model\Table;

/**
 * Proxy class to instantiate Monarc\Core's ObjectObjectService, with Monarc\FrontOffice's services
 * @package Monarc\FrontOffice\Service
 */
class ObjectObjectServiceFactory extends AbstractServiceFactory
{
    protected $class = ObjectObjectService::class;

    protected $ressources = [
        'table' => Table\ObjectObjectTable::class,
        'entity' => ObjectObject::class,
        'anrTable' => Table\AnrTable::class,
        'userAnrTable' => Table\UserAnrTable::class,
        'instanceTable' => Table\InstanceTable::class,
        'MonarcObjectTable' => Table\MonarcObjectTable::class,
        'childTable' => Table\MonarcObjectTable::class,
        'fatherTable' => Table\MonarcObjectTable::class,
    ];

    // TODO: A temporary solution to inject SharedEventManager. All the factories classes will be removed.
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $objectObjectService = parent::__invoke($container, $requestedName, $options);

        $objectObjectService->setSharedManager($container->get('EventManager')->getSharedManager());

        return $objectObjectService;
    }
}
