<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

/**
 * Factory class attached to AnrScaleTypeService
 * @package Monarc\FrontOffice\Service
 */
class AnrScaleTypeServiceFactory extends \Monarc\Core\Service\AbstractServiceFactory
{
    protected $ressources = [
        'table' => 'Monarc\FrontOffice\Model\Table\ScaleImpactTypeTable',
        'entity' => 'Monarc\FrontOffice\Model\Entity\ScaleImpactType',
        'anrTable' => 'Monarc\FrontOffice\Model\Table\AnrTable',
        'userAnrTable' => 'Monarc\FrontOffice\Model\Table\UserAnrTable',
        'scaleTable' => 'Monarc\FrontOffice\Model\Table\ScaleTable',
        'instanceTable' => 'Monarc\FrontOffice\Model\Table\InstanceTable',
        'instanceConsequenceService' => 'Monarc\FrontOffice\Service\AnrInstanceConsequenceService'
    ];
}
