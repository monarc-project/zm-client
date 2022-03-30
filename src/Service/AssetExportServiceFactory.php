<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2022 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Service\AbstractServiceFactory;
use Monarc\FrontOffice\Model\Table\AssetTable;
use Monarc\FrontOffice\Model\Entity\Asset;

class AssetExportServiceFactory extends AbstractServiceFactory
{
    protected $class = AssetExportService::class;

    protected $ressources = [
        'table' => AssetTable::class,
        'entity' => Asset::class,
        'amvService' => AnrAmvService::class,
    ];
}
