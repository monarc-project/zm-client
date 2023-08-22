<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Service\AbstractServiceFactory;
use Monarc\FrontOffice\Model\Table as DeprecatedTable;
use Monarc\FrontOffice\Table;
use Monarc\FrontOffice\Model\Entity\RecommandationSet;

/**
 * RecommandationSet Service Factory
 *
 * Class AnrRecommandationSetServiceFactory
 * @package Monarc\FrontOffice\Service
 */
class AnrRecommandationSetServiceFactory extends AbstractServiceFactory
{
    protected $ressources = [
        'table' => DeprecatedTable\RecommandationSetTable::class,
        'entity' => RecommandationSet::class,
        'anrTable' => Table\AnrTable::class,
        'userAnrTable' => Table\UserAnrTable::class,
        'recommendationTable' => DeprecatedTable\RecommandationTable::class,
    ];
}
