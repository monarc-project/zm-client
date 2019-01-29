<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Service;

/**
 * Factory class attached to AnrScaleTypeService
 * @package MonarcFO\Service
 */
class AnrScaleTypeServiceFactory extends \MonarcCore\Service\AbstractServiceFactory
{
    protected $ressources = [
        'table' => 'MonarcFO\Model\Table\ScaleImpactTypeTable',
        'entity' => 'MonarcFO\Model\Entity\ScaleImpactType',
        'anrTable' => 'MonarcFO\Model\Table\AnrTable',
        'userAnrTable' => 'MonarcFO\Model\Table\UserAnrTable',
        'scaleTable' => 'MonarcFO\Model\Table\ScaleTable',
        'instanceTable' => 'MonarcFO\Model\Table\InstanceTable',
        'instanceConsequenceService' => 'MonarcFO\Service\AnrInstanceConsequenceService'
    ];
}
