<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Service\AbstractServiceFactory;
use Monarc\FrontOffice\Table\UserAnrTable;
use Monarc\FrontOffice\Table\AnrTable;

/**
 * Factory class attached to AnrRolfRiskService
 * @package Monarc\FrontOffice\Service
 */
class AnrRolfRiskServiceFactory extends AbstractServiceFactory
{
    protected $ressources = [
        'entity' => 'Monarc\FrontOffice\Model\Entity\RolfRisk',
        'table' => 'Monarc\FrontOffice\Model\Table\RolfRiskTable',
        'anrTable' => AnrTable::class,
        'tagTable' => 'Monarc\FrontOffice\Model\Table\RolfTagTable',
        'rolfTagTable' => 'Monarc\FrontOffice\Model\Table\RolfTagTable',
        'MonarcObjectTable' => 'Monarc\FrontOffice\Model\Table\MonarcObjectTable',
        'measureTable' => 'Monarc\FrontOffice\Model\Table\MeasureTable',
        'referentialTable' => 'Monarc\FrontOffice\Model\Table\ReferentialTable',
        'instanceRiskOpTable' => 'Monarc\FrontOffice\Model\Table\InstanceRiskOpTable',
        'userAnrTable' => UserAnrTable::class,
        'instanceRiskOpService' => 'Monarc\FrontOffice\Service\AnrInstanceRiskOpService',
    ];
}
