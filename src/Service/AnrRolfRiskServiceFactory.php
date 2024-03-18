<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Service\AbstractServiceFactory;
use Monarc\FrontOffice\Table;
use Monarc\FrontOffice\Model\Table as DeprecatedTable;
use Monarc\FrontOffice\Entity\RolfRisk;

class AnrRolfRiskServiceFactory extends AbstractServiceFactory
{
    protected $ressources = [
        'entity' => RolfRisk::class,
        'table' => DeprecatedTable\RolfRiskTable::class,
        'anrTable' => Table\AnrTable::class,
        'tagTable' => DeprecatedTable\RolfTagTable::class,
        'rolfTagTable' => DeprecatedTable\RolfTagTable::class,
        'MonarcObjectTable' => Table\MonarcObjectTable::class,
        'measureTable' => DeprecatedTable\MeasureTable::class,
        'referentialTable' => DeprecatedTable\ReferentialTable::class,
        'instanceRiskOpTable' => Table\InstanceRiskOpTable::class,
        'userAnrTable' => Table\UserAnrTable::class,
        'instanceRiskOpService' => AnrInstanceRiskOpService::class,
    ];
}
