<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Service\AbstractServiceFactory;
use Monarc\FrontOffice\Model\Table;
use Monarc\FrontOffice\Model\Entity\Recommandation;

/**
 * Factory class attached to AnrRecommandationService
 * @package Monarc\FrontOffice\Service
 */
class AnrRecommandationServiceFactory extends AbstractServiceFactory
{
    protected $ressources = [
        'entity' => Recommandation::class,
        'table' => Table\RecommandationTable::class,
        'anrTable' => Table\AnrTable::class,
        'userAnrTable' => Table\UserAnrTable::class,
    ];
}
