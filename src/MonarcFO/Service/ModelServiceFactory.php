<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;

/**
 * Proxy class to instantiate MonarcCore's ModelService, with MonarcFO's services
 * @package MonarcFO\Service
 */
class ModelServiceFactory extends AbstractServiceFactory
{
    protected $class = "\\MonarcCore\\Service\\ModelService";

    protected $ressources = [
        'table' => 'MonarcCore\Model\Table\ModelTable',
        'entity' => 'MonarcCore\Model\Entity\Model',
        'anrService' => 'MonarcCore\Service\AnrService',
        'anrTable' => 'MonarcCore\Model\Table\AnrTable',
        'instanceRiskTable' => 'MonarcCore\Model\Table\InstanceRiskTable',
        'instanceRiskOpTable' => 'MonarcCore\Model\Table\InstanceRiskOpTable',
        'MonarcObjectTable' => 'MonarcCore\Model\Table\MonarcObjectTable',
        'amvTable' => 'MonarcCore\Model\Table\AmvTable',
        'clientTable' => '\MonarcFO\Model\Table\ClientTable'
    ];
}