<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Service;

use \MonarcCore\Service\AbstractServiceFactory;

/**
 * Proxy factory class to instantiate MonarcCore's InstanceConsequenceService using MonarcFO's services
 * @package MonarcFO\Service
 */
class AnrInstanceConsequenceServiceFactory extends AbstractServiceFactory
{
    protected $class = "\\MonarcCore\\Service\\InstanceConsequenceService";

    protected $ressources = [
        'table' => 'MonarcFO\Model\Table\InstanceConsequenceTable',
        'entity' => 'MonarcFO\Model\Entity\InstanceConsequence',
        'anrTable' => 'MonarcFO\Model\Table\AnrTable',
        'instanceTable' => 'MonarcFO\Model\Table\InstanceTable',
        'MonarcObjectTable' => 'MonarcFO\Model\Table\MonarcObjectTable',
        'scaleTable' => 'MonarcFO\Model\Table\ScaleTable',
        'scaleImpactTypeTable' => 'MonarcFO\Model\Table\ScaleImpactTypeTable',
    ];
}
