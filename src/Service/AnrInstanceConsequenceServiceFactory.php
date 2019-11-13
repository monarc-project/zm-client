<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use \Monarc\Core\Service\AbstractServiceFactory;

/**
 * Proxy factory class to instantiate Monarc\Core's InstanceConsequenceService using Monarc\FrontOffice's services
 * @package Monarc\FrontOffice\Service
 */
class AnrInstanceConsequenceServiceFactory extends AbstractServiceFactory
{
    protected $class = "\\Monarc\Core\\Service\\InstanceConsequenceService";

    protected $ressources = [
        'table' => 'Monarc\FrontOffice\Model\Table\InstanceConsequenceTable',
        'entity' => 'Monarc\FrontOffice\Model\Entity\InstanceConsequence',
        'anrTable' => 'Monarc\FrontOffice\Model\Table\AnrTable',
        'instanceTable' => 'Monarc\FrontOffice\Model\Table\InstanceTable',
        'MonarcObjectTable' => 'Monarc\FrontOffice\Model\Table\MonarcObjectTable',
        'scaleTable' => 'Monarc\FrontOffice\Model\Table\ScaleTable',
        'scaleImpactTypeTable' => 'Monarc\FrontOffice\Model\Table\ScaleImpactTypeTable',
    ];
}
