<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2021 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Service\AbstractServiceFactory;
use Monarc\FrontOffice\Model\Table;
use Monarc\FrontOffice\Model\Entity\InstanceRiskOwner;

/**
 * Factory class attached to AnrRiskOwnersService
 * @package Monarc\FrontOffice\Service
 */
class AnrRiskOwnersServiceFactory extends AbstractServiceFactory
{
    protected $ressources = [
        'table' => Table\InstanceRiskOwnerTable::class,
        'anrTable' => Table\AnrTable::class,
        'entity' => InstanceRiskOwner::class
    ];
}
