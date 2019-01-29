<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Service;

/**
 * Factory class attached to AnrScaleService
 * @package MonarcFO\Service
 */
class AnrScaleServiceFactory extends \MonarcCore\Service\AbstractServiceFactory
{
    protected $ressources = [
        'table' => 'MonarcFO\Model\Table\ScaleTable',
        'entity' => 'MonarcFO\Model\Entity\Scale',
        'anrTable' => 'MonarcFO\Model\Table\AnrTable',
        'AnrCheckStartedService' => 'MonarcFO\Service\AnrCheckStartedService',
        'scaleImpactTypeService' => 'MonarcFO\Service\AnrScaleTypeService',
        'config' => 'MonarcCore\Service\ConfigService',
    ];
}
