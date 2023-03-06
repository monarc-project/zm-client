<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Service\AbstractServiceFactory;
use Monarc\FrontOffice\Model\Table\ScaleImpactTypeTable;
use Monarc\FrontOffice\Model\Entity\ScaleImpactType;
use Monarc\FrontOffice\Model\Table\AnrTable;
use Monarc\FrontOffice\Model\Table\ScaleTable;
use Monarc\FrontOffice\Model\Table\InstanceTable;

/**
 * Factory class attached to AnrScaleTypeService
 * @package Monarc\FrontOffice\Service
 */
class AnrScaleTypeServiceFactory extends AbstractServiceFactory
{
    protected $ressources = [
        'table' => ScaleImpactTypeTable::class,
        'entity' => ScaleImpactType::class,
        'anrTable' => AnrTable::class,
        'scaleTable' => ScaleTable::class,
        'instanceTable' => InstanceTable::class,
        'instanceConsequenceService' => AnrInstanceConsequenceService::class,
        'instanceService' => AnrInstanceService::class,
    ];
}
