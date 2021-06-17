<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Service\AbstractServiceFactory;
use Monarc\FrontOffice\Model\Entity\InstanceRiskOp;
use Monarc\FrontOffice\Model\Table;

/**
 * Proxy factory class to instantiate Monarc\Core's InstanceRiskOpService using Monarc\FrontOffice's services
 * @package Monarc\FrontOffice\Service
 */
class AnrInstanceRiskOpServiceFactory extends AbstractServiceFactory
{
    protected $class = AnrInstanceRiskOpService::class;

    protected $ressources = [
        'table' => Table\InstanceRiskOpTable::class,
        'entity' => InstanceRiskOp::class,
        'anrTable' => Table\AnrTable::class,
        'userAnrTable' => Table\UserAnrTable::class,
        'instanceTable' => Table\InstanceTable::class,
        'MonarcObjectTable' => Table\MonarcObjectTable::class,
        'rolfRiskTable' => Table\RolfRiskTable::class,
        'rolfTagTable' => Table\RolfTagTable::class,
        'scaleTable' => Table\ScaleTable::class,
        'recommandationTable' => Table\RecommandationTable::class,
        'operationalInstanceRiskScaleTable' => Table\OperationalInstanceRiskScaleTable::class,
    ];
}
