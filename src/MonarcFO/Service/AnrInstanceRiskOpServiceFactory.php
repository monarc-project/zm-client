<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2018 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Service;

use \MonarcCore\Service\AbstractServiceFactory;

/**
 * Proxy factory class to instantiate MonarcCore's InstanceRiskOpService using MonarcFO's services
 * @package MonarcFO\Service
 */
class AnrInstanceRiskOpServiceFactory extends AbstractServiceFactory
{
    protected $class = "\\MonarcCore\\Service\\InstanceRiskOpService";

    protected $ressources = [
        'table' => 'MonarcFO\Model\Table\InstanceRiskOpTable',
        'entity' => 'MonarcFO\Model\Entity\InstanceRiskOp',
        'anrTable' => 'MonarcFO\Model\Table\AnrTable',
        'userAnrTable' => 'MonarcFO\Model\Table\UserAnrTable',
        'instanceTable' => 'MonarcFO\Model\Table\InstanceTable',
        'objectTable' => 'MonarcFO\Model\Table\ObjectTable',
        'rolfRiskTable' => 'MonarcFO\Model\Table\RolfRiskTable',
        'rolfTagTable' => 'MonarcFO\Model\Table\RolfTagTable',
        'scaleTable' => 'MonarcFO\Model\Table\ScaleTable',
        'recommandationTable' => 'MonarcFO\Model\Table\RecommandationTable',
    ];
}
