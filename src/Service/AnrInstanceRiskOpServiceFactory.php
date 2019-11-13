<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use \Monarc\Core\Service\AbstractServiceFactory;

/**
 * Proxy factory class to instantiate Monarc\Core's InstanceRiskOpService using Monarc\FrontOffice's services
 * @package Monarc\FrontOffice\Service
 */
class AnrInstanceRiskOpServiceFactory extends AbstractServiceFactory
{
    protected $class = "\\Monarc\Core\\Service\\InstanceRiskOpService";

    protected $ressources = [
        'table' => 'Monarc\FrontOffice\Model\Table\InstanceRiskOpTable',
        'entity' => 'Monarc\FrontOffice\Model\Entity\InstanceRiskOp',
        'anrTable' => 'Monarc\FrontOffice\Model\Table\AnrTable',
        'userAnrTable' => 'Monarc\FrontOffice\Model\Table\UserAnrTable',
        'instanceTable' => 'Monarc\FrontOffice\Model\Table\InstanceTable',
        'MonarcObjectTable' => 'Monarc\FrontOffice\Model\Table\MonarcObjectTable',
        'rolfRiskTable' => 'Monarc\FrontOffice\Model\Table\RolfRiskTable',
        'rolfTagTable' => 'Monarc\FrontOffice\Model\Table\RolfTagTable',
        'scaleTable' => 'Monarc\FrontOffice\Model\Table\ScaleTable',
        'recommandationTable' => 'Monarc\FrontOffice\Model\Table\RecommandationTable',
    ];
}
