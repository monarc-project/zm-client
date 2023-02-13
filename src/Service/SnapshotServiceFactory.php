<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Service\AbstractServiceFactory;
use Monarc\FrontOffice\Table\UserAnrTable;
use Monarc\FrontOffice\Model\Table\AnrTable;
use Monarc\FrontOffice\Model\Table\SnapshotTable;
use Monarc\FrontOffice\Model\Entity\Snapshot;

/**
 * Factory class attached to SnapshotService
 * @package Monarc\FrontOffice\Service
 */
class SnapshotServiceFactory extends AbstractServiceFactory
{
    protected $ressources = [
        'entity' => Snapshot::class,
        'table' => SnapshotTable::class,
        'anrTable' => AnrTable::class,
        'userAnrTable' => UserAnrTable::class,
        'anrService' => AnrService::class,
    ];
}
