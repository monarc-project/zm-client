<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Service\AbstractServiceFactory;
use Monarc\FrontOffice\Table\AnrTable;
use Monarc\FrontOffice\Table\UserAnrTable;

/**
 * Factory class attached to AnrRecommandationRiskService
 * @package Monarc\FrontOffice\Service
 */
class AnrRecommandationRiskServiceFactory extends AbstractServiceFactory
{
    protected $ressources = [
        'entity' => 'Monarc\FrontOffice\Model\Entity\RecommandationRisk',
        'table' => 'Monarc\FrontOffice\Model\Table\RecommandationRiskTable',
        'anrTable' => AnrTable::class,
        'userAnrTable' => UserAnrTable::class,
        'recommendationTable' => 'Monarc\FrontOffice\Model\Table\RecommandationTable',
        'recommendationHistoricTable' => 'Monarc\FrontOffice\Model\Table\RecommendationHistoricTable',
        'instanceRiskTable' => 'Monarc\FrontOffice\Model\Table\InstanceRiskTable',
        'instanceRiskOpTable' => 'Monarc\FrontOffice\Model\Table\InstanceRiskOpTable',
        'anrService' => 'Monarc\FrontOffice\Service\AnrService',
        'instanceTable' => 'Monarc\FrontOffice\Model\Table\InstanceTable',
    ];
}
