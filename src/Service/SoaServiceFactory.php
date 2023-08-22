<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2022 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Service\AbstractServiceFactory;
use Monarc\FrontOffice\Model\Table as DeprecatedTable;
use Monarc\FrontOffice\Table;
use Monarc\FrontOffice\Model\Entity\Soa;
use Monarc\FrontOffice\Table\UserAnrTable;

class SoaServiceFactory extends AbstractServiceFactory
{
    protected $ressources = [
        'entity' => Soa::class,
        'table' => DeprecatedTable\SoaTable::class,
        'anrTable' => DeprecatedTable\AnrTable::class,
        'userAnrTable' => UserAnrTable::class,
        'soaScaleCommentTable' => Table\SoaScaleCommentTable::class,
    ];
}
