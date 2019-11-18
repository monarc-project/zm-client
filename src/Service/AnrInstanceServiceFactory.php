<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Interop\Container\ContainerInterface;
use Monarc\Core\Service\AbstractServiceFactory;
use Monarc\Core\Service\ConfigService;
use Monarc\Core\Service\TranslateService;
use Monarc\FrontOffice\Model\Entity\Instance;
use Monarc\FrontOffice\Model\Entity\InstanceConsequence;
use Monarc\FrontOffice\Model\Table;

/**
 * Factory class attached to AnrInstanceService
 * @package Monarc\FrontOffice\Service
 */
class AnrInstanceServiceFactory extends AbstractServiceFactory
{
    protected $ressources = [
        // Tables & Entities
        'table' => Table\InstanceTable::class,
        'entity' => Instance::class,
        'anrTable' => Table\AnrTable::class,
        'assetTable' => Table\AssetTable::class,
        'userAnrTable' => Table\UserAnrTable::class,
        'amvTable' => Table\AmvTable::class,
        'objectTable' => Table\MonarcObjectTable::class,
        'scaleTable' => Table\ScaleTable::class,
        'scaleCommentTable' => Table\ScaleCommentTable::class,
        'scaleImpactTypeTable' => Table\ScaleImpactTypeTable::class,
        'instanceConsequenceTable' => Table\InstanceConsequenceTable::class,
        'instanceRiskTable' => Table\InstanceRiskTable::class,
        'instanceConsequenceEntity' => InstanceConsequence::class,
        'recommandationRiskTable' => Table\RecommandationRiskTable::class,
        'recommandationTable' => Table\RecommandationTable::class,
        'recommandationSetTable' => Table\RecommandationSetTable::class,
        'questionTable' => Table\QuestionTable::class,
        'questionChoiceTable' => Table\QuestionChoiceTable::class,
        'threatTable' => Table\ThreatTable::class,
        'interviewTable' => Table\InterviewTable::class,
        'themeTable' => Table\ThemeTable::class,
        'deliveryTable' => Table\DeliveryTable::class,
        'referentialTable' => Table\ReferentialTable::class,
        'soaCategoryTable' => Table\SoaCategoryTable::class,
        'measureTable' => Table\MeasureTable::class,
        'measureMeasureTable' => Table\MeasureMeasureTable::class,
        'soaTable' => Table\SoaTable::class,

        // Services
        'instanceConsequenceService' => AnrInstanceConsequenceService::class,
        'instanceRiskService' => AnrInstanceRiskService::class,
        'instanceRiskOpService' => AnrInstanceRiskOpService::class,
        'objectObjectService' => ObjectObjectService::class,
        'translateService' => TranslateService::class,
        'instanceTable' => Table\InstanceTable::class,
        'recordService' => AnrRecordService::class,
        'configService' => ConfigService::class,

        // Export (Services)
        'objectExportService' => ObjectExportService::class,
        'amvService' => AmvService::class,
        'scaleCommentService' => AnrScaleCommentService::class,
    ];

    // TODO: A temporary solution to inject SharedEventManager. All the factories classes will be removed.
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $objectObjectService = parent::__invoke($container, $requestedName, $options);

        $objectObjectService->setSharedManager($container->get('EventManager')->getSharedManager());

        return $objectObjectService;
    }
}
