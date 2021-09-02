<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Service\AbstractServiceFactory;

/**
 * Factory class attached to AnrScaleService
 * @package Monarc\FrontOffice\Service
 */
class AnrScaleServiceFactory extends AbstractServiceFactory
{
    protected $ressources = [
        'table' => 'Monarc\FrontOffice\Model\Table\ScaleTable',
        'entity' => 'Monarc\FrontOffice\Model\Entity\Scale',
        'anrTable' => 'Monarc\FrontOffice\Model\Table\AnrTable',
        'anrCheckStartedService' => 'Monarc\FrontOffice\Service\AnrCheckStartedService',
        'scaleImpactTypeService' => 'Monarc\FrontOffice\Service\AnrScaleTypeService',
        'config' => 'Monarc\Core\Service\ConfigService',
    ];
}
