<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Import\Service;

use Monarc\Core\Exception\Exception;
use Monarc\Core\Service\Traits\RiskCalculationTrait;
use Monarc\FrontOffice\Entity;
use Monarc\FrontOffice\Import\Processor;
use Monarc\FrontOffice\Import\Traits;
use Monarc\FrontOffice\Table;
use Monarc\FrontOffice\Service;

class InstanceImportService
{
    use RiskCalculationTrait;
    use Traits\EvaluationConverterTrait;
    use Traits\ImportFileContentTrait;
    use Traits\ImportValidationTrait;
    use Traits\ImportDataStructureAdapterTrait;

    public function __construct(
        private Processor\AssetImportProcessor $assetImportProcessor,
        private Processor\ThreatImportProcessor $threatImportProcessor,
        private Processor\VulnerabilityImportProcessor $vulnerabilityImportProcessor,
        private Processor\ReferentialImportProcessor $referentialImportProcessor,
        private Processor\InformationRiskImportProcessor $informationRiskImportProcessor,
        private Processor\RolfTagImportProcessor $rolfTagImportProcessor,
        private Processor\OperationalRiskImportProcessor $operationalRiskImportProcessor,
        private Processor\RecommendationImportProcessor $recommendationImportProcessor,
        private Processor\ObjectCategoryImportProcessor $objectCategoryImportProcessor,
        private Processor\ObjectImportProcessor $objectImportProcessor,
        private Processor\AnrInstanceMetadataFieldImportProcessor $anrInstanceMetadataFieldImportProcessor,
        private Processor\AnrMethodStepImportProcessor $anrMethodStepImportProcessor,
        private Processor\InstanceImportProcessor $instanceImportProcessor,
        private Processor\InstanceConsequenceImportProcessor $instanceConsequenceImportProcessor,
        private Processor\ScaleImportProcessor $scaleImportProcessor,
        private Processor\OperationalRiskScaleImportProcessor $operationalRiskScaleImportProcessor,
        private Processor\OperationalInstanceRiskImportProcessor $operationalInstanceRiskImportProcessor,
        private Processor\SoaImportProcessor $soaImportProcessor,
        private Service\AnrRecordService $anrRecordService,
        private Table\InstanceTable $instanceTable,
        private Table\AnrTable $anrTable,
    ) {
    }

    /**
     * Available import modes: 'merge', which will update the existing instances using the file's data,
     * or 'duplicate' which will create a new instance using the data.
     *
     * @return array An array where the first key is the generated IDs, and the second are import errors
     */
    public function importFromFile(Entity\Anr $anr, array $importParams): array
    {
        // Mode may either be 'merge' or 'duplicate'
        $mode = empty($importParams['mode']) ? 'merge' : $importParams['mode'];

        /*
         * The object may be imported at the root, or under an existing instance in the ANR instances tree
         */
        $parentInstance = null;
        if (!empty($importParams['idparent'])) {
            /** @var Entity\Instance $parentInstance */
            $parentInstance = $this->instanceTable->findById((int)$importParams['idparent']);
        }

        // We can have multiple files imported with the same password (we'll emit warnings if the password mismatches)
        if (empty($importParams['file'])) {
            throw new Exception('File missing', 412);
        }

        $createdInstancesIds = [];
        $importErrors = [];
        foreach ($importParams['file'] as $file) {
            if (isset($file['error']) && $file['error'] === UPLOAD_ERR_OK && file_exists($file['tmp_name'])) {
                $data = $this->getArrayDataOfJsonFileContent($file['tmp_name'], $importParams['password'] ?? null);

                if ($data !== false) {
                    $createdInstancesIds = $this->importFromArray($data, $anr, $parentInstance, $mode);
                } else {
                    $importErrors[] = 'The file "' . $file['name'] . '" can\'t be imported';
                }
            }
        }

        return [$createdInstancesIds, $importErrors];
    }

    /**
     * @param null|Entity\Instance $parentInstance The parent instance, that should be imported, null if it's root.
     * @param string $importMode Import mode, either 'merge' or 'duplicate'
     *
     * @return array An array of created instances IDs
     */
    private function importFromArray(
        array $data,
        Entity\Anr $anr,
        ?Entity\Instance $parentInstance,
        string $importMode
    ): array {
        $this->setAndValidateImportingDataVersion($data);
        $this->validateIfImportIsPossible($anr, $parentInstance, $data);

        $withEval = $data['withEval'] ?? $data['with_eval'];

        $createdInstances = [];

        if ($data['type'] === 'anr') {
            $createdInstances = $this->isImportingDataVersionLowerThan('2.13.1')
                ? $this->importAnrFromArray($data, $anr, $parentInstance, $importMode)
                : $this->processAnrImport($anr, $data, $parentInstance, $importMode);
        } elseif ($data['type'] === 'instance') {
            if ($withEval) {
                $this->scaleImportProcessor->prepareScalesCache($anr, $data);
                $this->operationalRiskScaleImportProcessor->prepareExternalOperationalRiskScalesDataCache(
                    $anr,
                    $data
                );
            }
            if ($this->isImportingDataVersionLowerThan('2.13.1')) {
                $data['instance'] = $this->adaptOldInstanceDataToNewFormat($data, $anr->getLanguage());
            }
            $createdInstances[] = $this->instanceImportProcessor
                ->processInstanceData($anr, $data['instance'], $parentInstance, $importMode, $withEval, false);
        }

        $this->anrTable->flush();

        $result = [];
        foreach ($createdInstances as $instance) {
            $result[] = $instance->getId();
        }

        return $result;
    }

    /** New structure full analysis import process, from v2.13.1. */
    private function processAnrImport(
        Entity\Anr $anr,
        array $data,
        ?Entity\Instance $parentInstance,
        string $importMode
    ): array {
        if ($data['withMethodSteps']) {
            /* Process all the method's steps. */
            $this->anrMethodStepImportProcessor->processAnrMethodStepsData($anr, $data['method']);
        }
        if ($data['withEval']) {
            /* Process all the analysis' thresholds. */
            $this->anrMethodStepImportProcessor->processThresholdsData($anr, $data['thresholds']);

            /* Apply the importing information risks scales. */
            $this->scaleImportProcessor->applyNewScalesFromData($anr, $data['scales']);

            /* Apply the importing operational risks scales. */
            $this->operationalRiskScaleImportProcessor->adjustOperationalRisksScaleValuesBasedOnNewScales($anr, $data);
            $this->operationalRiskScaleImportProcessor->updateOperationalRisksScalesAndRelatedInstances($anr, $data);

        }
        if ($data['withInterviews']) {
            /* Process the interviews' data. */
            $this->anrMethodStepImportProcessor->processInterviewsData($anr, $data['interviews']);
        }
        if ($data['withKnowledgeBase']) {
            /* Process the Knowledge Base data. */
            $this->processKnowledgeBaseData($anr, $data['knowledgeBase']);
        }
        if ($data['withLibrary']) {
            /* Process the Assets Library data. */
            $this->objectCategoryImportProcessor
                ->processObjectCategoriesData($anr, $data['library']['categories'], $importMode);
        }
        /* Process the analysis metadata fields data. */
        $this->anrInstanceMetadataFieldImportProcessor->processAnrInstanceMetadataFields(
            $anr,
            $data['anrInstanceMetadataFields']
        );

        /* Process the Instances, Instance Risks, Consequences and evaluations data. */
        $result = $this->instanceImportProcessor
            ->processInstancesData($anr, $data['instances'], $parentInstance, $importMode, $data['withEval']);

        /* Process Soa and SoaScaleComments data. */
        if (!empty($data['soaScaleComments'])) {
            $this->soaImportProcessor->mergeSoaScaleComments($anr, $data['soaScaleComments'], false);
        }

        /* import the GDPR records. */
        foreach ($data['gdprRecords'] ?? [] as $gdprRecordData) {
            try {
                $this->anrRecordService->importFromArray($gdprRecordData, $anr->getId());
            } catch (\Throwable) {
            }
        }

        // TODO: add recommendationHistory to the export and process them.

        return $result;
    }

    private function processKnowledgeBaseData(Entity\Anr $anr, array $knowledgeBaseData): void
    {
        $this->assetImportProcessor->processAssetsData($anr, $knowledgeBaseData['assets']);
        $this->threatImportProcessor->processThreatsData($anr, $knowledgeBaseData['threats']);
        $this->vulnerabilityImportProcessor->processVulnerabilitiesData($anr, $knowledgeBaseData['vulnerabilities']);
        $this->referentialImportProcessor->processReferentialsData($anr, $knowledgeBaseData['referentials']);
        $this->informationRiskImportProcessor->processInformationRisksData(
            $anr,
            $knowledgeBaseData['informationRisks']
        );
        $this->rolfTagImportProcessor->processRolfTagsData($anr, $knowledgeBaseData['rolfTags']);
        $this->operationalRiskImportProcessor->processOperationalRisksData(
            $anr,
            $knowledgeBaseData['operationalRisks']
        );
        $this->recommendationImportProcessor->processRecommendationSetsData(
            $anr,
            $knowledgeBaseData['recommendationSets']
        );
    }

    /**
     * Supports the import data structure prior v2.13.1.
     *
     * @return Entity\Instance[]
     */
    private function importAnrFromArray(
        array $data,
        Entity\Anr $anr,
        ?Entity\Instance $parentInstance,
        string $modeImport
    ): array {
        /* Import the method steps. */
        if (!empty($data['method'])) {
            $this->anrMethodStepImportProcessor->processAnrMethodStepsData($anr, $data['method']);
        }

        /* Import referential. */
        if (!empty($data['referentials'])) {
            $this->referentialImportProcessor->processReferentialsData($anr, $data['referentials']);
        }

        /* Import measures and soa categories. */
        foreach ($data['measures'] ?? [] as $measureData) {
            $referential = $this->referentialImportProcessor->getReferentialFromCache(
                $anr,
                $measureData['referential']
            );
            if ($referential !== null) {
                $this->referentialImportProcessor->processMeasureData($anr, $referential, $measureData);
            }
        }

        foreach ($data['measuresMeasures'] ?? [] as $measureMeasureData) {
            $measure = $this->referentialImportProcessor->getMeasureFromCache($anr, $measureMeasureData['father']);
            if ($measure !== null) {
                $this->referentialImportProcessor->processLinkedMeasures(
                    $anr,
                    $measure,
                    ['linkedMeasures' => [['uuid' => $measureMeasureData['child']]]]
                );
            }
        }

        /* Import SOA Scale Comments if they are passed. Only in the new structure, when the functionality exists. */
        if (!empty($data['soaScaleComment'])) {
            $this->soaImportProcessor->mergeSoaScaleComments($anr, $data['soaScaleComment'], true);
        }

        /* Import the Statement of Applicability (SOA). */
        if (!empty($data['soas'])) {
            $this->soaImportProcessor->processSoasData($anr, $data['soas']);
        }

        /* Import the GDPR records. */
        if (!empty($data['records'])) {
            foreach ($data['records'] as $v) {
                $this->anrRecordService->importFromArray($v, $anr->getId());
            }
        }

        /* Import AnrInstanceMetadataFields. */
        if (!empty($data['anrMetadatasOnInstances'])) {
            $this->anrInstanceMetadataFieldImportProcessor->processAnrInstanceMetadataFields(
                $anr,
                $data['anrMetadatasOnInstances']
            );
        }

        $withEval = (bool)$data['with_eval'];
        /* Import scales. */
        if ($withEval && !empty($data['scales'])) {
            /* Adjust the values of information risks' scales. */
            $data['scales'] = $this->adoptOldScalesDataStructureToNewFormat($data, $anr->getLanguage());
            $this->scaleImportProcessor->applyNewScalesFromData($anr, $data['scales']);

            /* Adjust the values of operational risks' scales. */
            $this->operationalRiskScaleImportProcessor->adjustOperationalRisksScaleValuesBasedOnNewScales($anr, $data);
            $this->operationalRiskScaleImportProcessor->updateOperationalRisksScalesAndRelatedInstances($anr, $data);
        }

        $instances = [];
        $areRecommendationsProcessed = false;
        usort($data['instances'], static function ($a, $b) {
            return $a['instance']['position'] <=> $b['instance']['position'];
        });
        foreach ($data['instances'] as $instanceData) {
            $instanceData = $this->adaptOldInstanceDataToNewFormat($instanceData, $anr->getLanguage());
            if ($withEval && !empty($instanceData['recs']) && !$areRecommendationsProcessed) {
                /* Process all the recommendations' data that are under instance */
                $recommendationSetsData = $this->adaptOldRecommendationSetsDataToNewFormat($data);
                $this->recommendationImportProcessor->processRecommendationSetsData($anr, $recommendationSetsData);
                $areRecommendationsProcessed = true;
            }

            $instances[] = $this->instanceImportProcessor
                ->processInstanceData($anr, $instanceData, $parentInstance, $modeImport, $withEval);
        }

        return $instances;
    }
}
