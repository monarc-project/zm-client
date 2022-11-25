<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Interop\Container\ContainerInterface;
use Monarc\Core\Service\AbstractServiceFactory;
use Monarc\Core\Service\ConfigService;
use Monarc\Core\Service\OperationalRiskScalesExportService;
use Monarc\Core\Service\AnrMetadatasOnInstancesExportService;
use Monarc\Core\Service\SoaScaleCommentExportService;
use Monarc\Core\Service\TranslateService;
use Monarc\FrontOffice\Model\Entity\Instance;
use Monarc\FrontOffice\Model\Entity\InstanceConsequence;
use Monarc\FrontOffice\Model\Table as DeprecatedTable;
use Monarc\FrontOffice\Table;

class AnrInstanceServiceFactory extends AbstractServiceFactory
{
    protected $ressources = [
        // Tables & Entities
        'table' => DeprecatedTable\InstanceTable::class,
        'entity' => Instance::class,
        'anrTable' => DeprecatedTable\AnrTable::class,
        'userAnrTable' => Table\UserAnrTable::class,
        'amvTable' => Table\AmvTable::class,
        'objectTable' => Table\MonarcObjectTable::class,
        'scaleTable' => DeprecatedTable\ScaleTable::class,
        'scaleImpactTypeTable' => DeprecatedTable\ScaleImpactTypeTable::class,
        'instanceConsequenceTable' => DeprecatedTable\InstanceConsequenceTable::class,
        'instanceRiskTable' => DeprecatedTable\InstanceRiskTable::class,
        'instanceRiskOpTable' => DeprecatedTable\InstanceRiskOpTable::class,
        'instanceConsequenceEntity' => InstanceConsequence::class,
        'recommendationRiskTable' => DeprecatedTable\RecommandationRiskTable::class,
        'recommendationTable' => DeprecatedTable\RecommandationTable::class,
        'recommendationSetTable' => DeprecatedTable\RecommandationSetTable::class,
        'recommendationRiskTable' => Table\RecommandationRiskTable::class,
        'recommendationTable' => Table\RecommandationTable::class,
        'recommendationSetTable' => Table\RecommandationSetTable::class,
        'themeTable' => Table\ThemeTable::class,
        'translationTable' => Table\TranslationTable::class,
        'instanceMetadataTable' => Table\InstanceMetadataTable::class,

        // Services
        'instanceConsequenceService' => AnrInstanceConsequenceService::class,
        'instanceRiskService' => AnrInstanceRiskService::class,
        'instanceRiskOpService' => AnrInstanceRiskOpService::class,
        'translateService' => TranslateService::class,
        'configService' => ConfigService::class,
        'operationalRiskScalesExportService' => OperationalRiskScalesExportService::class,
        'anrMetadatasOnInstancesExportService' => AnrMetadatasOnInstancesExportService::class,

        // Export Services
        'objectExportService' => ObjectExportService::class,
        'amvService' => AnrAmvService::class,
    ];
}
