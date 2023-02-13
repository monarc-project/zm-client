<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Service\AbstractServiceFactory;
use Monarc\FrontOffice\Model\Entity\InstanceConsequence;
use Monarc\FrontOffice\Model\Table;

/**
 * Proxy factory class to instantiate Monarc\Core's InstanceConsequenceService using Monarc\FrontOffice's services
 * @package Monarc\FrontOffice\Service
 */
class AnrInstanceConsequenceServiceFactory extends AbstractServiceFactory
{
    protected $ressources = [
        'table' => Table\InstanceConsequenceTable::class,
        'entity' => InstanceConsequence::class,
        'anrTable' => Table\AnrTable::class,
        'instanceTable' => Table\InstanceTable::class,
        'MonarcObjectTable' => Table\MonarcObjectTable::class,
        'scaleTable' => Table\ScaleTable::class,
        'scaleImpactTypeTable' => Table\ScaleImpactTypeTable::class,
        'scaleCommentTable' => Table\ScaleCommentTable::class,
    ];
}
