<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Service\AbstractServiceFactory;

/**
 * Proxy class to instantiate Monarc\Core's ModelService, with Monarc\FrontOffice's services
 * @package Monarc\FrontOffice\Service
 */
class ModelServiceFactory extends AbstractServiceFactory
{
    protected $class = "\\Monarc\Core\\Service\\ModelService";

    protected $ressources = [
        'table' => 'Monarc\Core\Model\Table\ModelTable',
        'entity' => 'Monarc\Core\Model\Entity\Model',
        'anrService' => 'Monarc\Core\Service\AnrService',
        'anrTable' => 'Monarc\Core\Model\Table\AnrTable',
        'instanceRiskTable' => 'Monarc\Core\Model\Table\InstanceRiskTable',
        'instanceRiskOpTable' => 'Monarc\Core\Model\Table\InstanceRiskOpTable',
        'MonarcObjectTable' => 'Monarc\Core\Model\Table\MonarcObjectTable',
        'amvTable' => 'Monarc\Core\Model\Table\AmvTable',
        'clientTable' => '\Monarc\FrontOffice\Model\Table\ClientTable'
    ];
}
