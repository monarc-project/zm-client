<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Import\Processor;

use Monarc\Core\Entity\ScaleSuperClass;
use Monarc\FrontOffice\Entity;
use Monarc\FrontOffice\Import\Helper\ImportCacheHelper;
use Monarc\FrontOffice\Import\Service\InstanceImportService;
use Monarc\FrontOffice\Import\Traits\EvaluationConverterTrait;
use Monarc\FrontOffice\Service\AnrInstanceConsequenceService;

class InstanceConsequenceImportProcessor
{
    use EvaluationConverterTrait;

    public function __construct(
        private AnrInstanceConsequenceService $anrInstanceConsequenceService,
        private ScaleImportProcessor $scaleImportProcessor,
        private ImportCacheHelper $importCacheHelper
    ) {
    }

    public function processInstanceConsequencesData(
        Entity\Instance $instance,
        array $instanceConsequencesData,
        ?Entity\Instance $siblingInstance,
    ): void {
        if ($this->importCacheHelper->getValueFromArrayCache('with_eval')) {
            foreach ($instanceConsequencesData as $instanceConsequenceData) {
                $this->processInstanceConsequenceData($instance, $instanceConsequenceData);
            }
        } elseif ($siblingInstance === null) {
            $this->createInstanceConsequencesBasedOnExistingImpactTypes($instance);
        } else {
            $this->createInstanceConsequencesBasedOnSiblingInstance($instance, $siblingInstance);
        }
    }

    public function processInstanceConsequenceData(
        Entity\Instance $instance,
        array $instanceConsequenceData
    ): ?Entity\InstanceConsequence {
        /** @var Entity\Anr $anr */
        $anr = $instance->getAnr();
        $scaleImpactType = $this->scaleImportProcessor
            ->getScaleImpactTypeFromCacheByLabel($anr, $instanceConsequenceData['scaleImpactType']['label']);
        if ($scaleImpactType === null) {
            return null;
        }

        /* For the instances import the values have to be converted to local scales. */
        if ($this->importCacheHelper
            ->getValueFromArrayCache('import_type') === InstanceImportService::IMPORT_TYPE_INSTANCE
        ) {
            $this->convertInstanceConsequencesEvaluations($instanceConsequenceData);
        }

        return $this->anrInstanceConsequenceService->createInstanceConsequence(
            $instance,
            $scaleImpactType,
            (bool)$instanceConsequenceData['isHidden'],
            $instanceConsequenceData,
            false
        );
    }

    public function createInstanceConsequencesBasedOnExistingImpactTypes(Entity\Instance $instance): void
    {
        /** @var Entity\Anr $anr */
        $anr = $instance->getAnr();
        foreach ($this->scaleImportProcessor->getScalesImpactTypesFromCache($anr) as $scaleImpactType) {
            $this->anrInstanceConsequenceService
                ->createInstanceConsequence($instance, $scaleImpactType, $scaleImpactType->isHidden());
        }
    }

    public function createInstanceConsequencesBasedOnSiblingInstance(
        Entity\Instance $instance,
        Entity\Instance $siblingInstance
    ): void {
        foreach ($siblingInstance->getInstanceConsequences() as $instanceConsequence) {
            /** @var Entity\ScaleImpactType $scalesImpactType */
            $scalesImpactType = $instanceConsequence->getScaleImpactType();
            $this->anrInstanceConsequenceService->createInstanceConsequence(
                $instance,
                $scalesImpactType,
                $instanceConsequence->isHidden(),
                [
                    'confidentiality' => $instanceConsequence->getConfidentiality(),
                    'integrity' => $instanceConsequence->getIntegrity(),
                    'availability' => $instanceConsequence->getAvailability(),
                ]
            );
        }
    }

    private function convertInstanceConsequencesEvaluations(array &$instanceData): void
    {
        $currentScaleRange = $this->importCacheHelper
            ->getItemFromArrayCache('current_scales_data_by_type')[ScaleSuperClass::TYPE_IMPACT];
        $externalScaleRange = $this->importCacheHelper
            ->getItemFromArrayCache('external_scales_data_by_type')[ScaleSuperClass::TYPE_IMPACT];
        foreach (['confidentiality', 'integrity', 'availability'] as $propertyName) {
            $instanceData[$propertyName] = $this->convertValueWithinNewScalesRange(
                $instanceData[$propertyName],
                $externalScaleRange['min'],
                $externalScaleRange['max'],
                $currentScaleRange['min'],
                $currentScaleRange['max'],
            );
        }
    }
}
