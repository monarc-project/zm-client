<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Service\AbstractServiceFactory;
use Monarc\FrontOffice\Table\AnrTable;
use Monarc\FrontOffice\Table\UserAnrTable;

/**
 * Factory class attached to AnrMeasureService
 * @package Monarc\FrontOffice\Service
 */
class AnrMeasureServiceFactory extends AbstractServiceFactory
{
    protected $ressources = [
        'entity' => 'Monarc\FrontOffice\Entity\Measure',
        'table' => 'Monarc\FrontOffice\Model\Table\MeasureTable',
        'anrTable' => AnrTable::class,
        'userAnrTable' => UserAnrTable::class,
        'soaEntity' => 'Monarc\FrontOffice\Entity\Soa',
        'soaTable' => 'Monarc\FrontOffice\Model\Table\SoaTable',
        'soaCategoryTable' => 'Monarc\FrontOffice\Model\Table\SoaCategoryTable',
    ];
}
