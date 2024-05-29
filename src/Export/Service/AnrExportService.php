<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Export\Service;

use Monarc\Core\Helper\EncryptDecryptHelperTrait;
use Monarc\Core\Service\ConfigService;
use Monarc\FrontOffice\Entity;
use Monarc\FrontOffice\Export\Service\Traits as ExportTrait;
use Monarc\FrontOffice\Model\Table as DeprecatedTable;
use Monarc\FrontOffice\Service\AnrRecordService;
use Monarc\FrontOffice\Table;

class AnrExportService
{
    use EncryptDecryptHelperTrait;
    use ExportTrait\InformationRiskExportTrait;
    use ExportTrait\OperationalRiskExportTrait;
    use ExportTrait\ObjectExportTrait;
    use ExportTrait\InstanceExportTrait;
    use ExportTrait\AssetExportTrait;
    use ExportTrait\ThreatExportTrait;
    use ExportTrait\VulnerabilityExportTrait;
    use ExportTrait\ScaleExportTrait;
    use ExportTrait\OperationalRiskScaleExportTrait;
    use ExportTrait\RecommendationExportTrait;

    public function __construct(
        private Table\AnrTable $anrTable,
        private Table\AssetTable $assetTable,
        private Table\ThreatTable $threatTable,
        private Table\VulnerabilityTable $vulnerabilityTable,
        private Table\AmvTable $amvTable,
        private Table\RolfTagTable $rolfTagTable,
        private Table\RolfRiskTable $rolfRiskTable,
        private Table\ReferentialTable $referentialTable,
        private Table\RecommendationSetTable $recommendationSetTable,
        private Table\AnrInstanceMetadataFieldTable $anrInstanceMetadataFieldTable,
        private Table\ObjectCategoryTable $objectCategoryTable,
        private Table\InstanceTable $instanceTable,
        private Table\ScaleTable $scaleTable,
        private Table\OperationalRiskScaleTable $operationalRiskScaleTable,
        private Table\SoaScaleCommentTable $soaScaleCommentTable,
        private Table\DeliveryTable $deliveryTable,
        private DeprecatedTable\SoaTable $soaTable,
        private DeprecatedTable\QuestionTable $questionTable,
        private DeprecatedTable\interviewTable $interviewTable,
        private DeprecatedTable\RecordTable $recordTable,
        private AnrRecordService $anrRecordService,
        private ConfigService $configService
    ) {
    }

    /**
     * @return array Result contains:
     * [
     *     'filename' => {the generated filename},
     *     'content' => {json encoded string, encrypted if password is set}
     * ]
     */
    public function export(Entity\Anr $anr, array $exportParams): array
    {
        $jsonResult = json_encode($this->prepareExportData($anr, $exportParams), JSON_THROW_ON_ERROR);

        return [
            'filename' => preg_replace("/[^a-z0-9\._-]+/i", '', $anr->getLabel()),
            'content' => empty($exportParams['password'])
                ? $jsonResult
                : $this->encrypt($jsonResult, $exportParams['password']),
        ];
    }

    private function prepareExportData(Entity\Anr $anr, array $exportParams): array
    {
        $withEval = !empty($exportParams['assessments']);
        $withControls = $withEval && !empty($exportParams['controls']);
        $withRecommendations = $withEval && !empty($exportParams['recommendations']);
        $withMethodSteps = $withEval && !empty($exportParams['methodSteps']);
        $withInterviews = $withEval && !empty($exportParams['interviews']);
        $withSoas = $withEval && !empty($exportParams['soas']);
        $withRecords = $withEval && !empty($exportParams['records']);
        $withLibrary = !empty($exportParams['assetsLibrary']);
        $withKnowledgeBase = !empty($exportParams['knowledgeBase']);

        return [
            'type' => 'anr',
            'monarc_version' => $this->configService->getAppVersion()['appVersion'],
            'exportDatetime' => (new \DateTime())->format('Y-m-d H:i:s'),
            'withEval' => $withEval,
            'withControls' => $withControls,
            'withRecommendations' => $withRecommendations,
            'withMethodSteps' => $withMethodSteps,
            'withInterviews' => $withInterviews,
            'withSoas' => $withSoas,
            'withRecords' => $withRecords,
            'withLibrary' => $withLibrary,
            'withKnowledge' => $withKnowledgeBase,
            'languageCode' => $anr->getLanguageCode(),
            'languageIndex' => $anr->getLanguage(),
            'knowledgeBase' => $withKnowledgeBase
                ? $this->prepareKnowledgeBaseData($anr, $withEval, $withControls, $withRecommendations)
                : [],
            'library' => $withLibrary ? $this->prepareLibraryData($anr) : [],
            'instances' => $this
                ->prepareInstancesData($anr, !$withLibrary, $withEval, $withControls, $withRecommendations),
            'anrInstanceMetadataFields' => $this->prepareAnrInstanceMetadataFieldsData($anr),
            'scales' => $withEval ? $this->prepareScalesData($anr) : [],
            'operationalRiskScales' => $withEval ? $this->prepareOperationalRiskScalesData($anr) : [],
            'soaScaleComments' => $withSoas ? $this->prepareSoaScaleCommentsData($anr) : [],
            'soas' => $withSoas ? $this->prepareSoasData($anr) : [],
            'method' => $withMethodSteps ? $this->prepareMethodData($anr) : [],
            'thresholds' => $withEval ? $this->prepareAnrTrashholdsData($anr) : [],
            'interviews' => $withInterviews ? $this->prepareInterviewsData($anr) : [],
            'gdprRecords' => $withRecords ? $this->prepareGdprRecordsData($anr) : [],
        ];
    }

    private function prepareAnrInstanceMetadataFieldsData(Entity\Anr $anr): array
    {
        $result = [];
        /** @var Entity\AnrInstanceMetadataField $anrInstanceMetadata */
        foreach ($this->anrInstanceMetadataFieldTable->findByAnr($anr) as $anrInstanceMetadata) {
            $result[] = ['label' => $anrInstanceMetadata->getLabel()];
        }

        return $result;
    }

    private function prepareKnowledgeBaseData(
        Entity\Anr $anr,
        bool $withEval,
        bool $withControls,
        bool $withRecommendations
    ): array {
        return [
            'assets' => $this->prepareAssetsData($anr),
            'threats' => $this->prepareThreatsData($anr, $withEval),
            'vulnerabilities' => $this->prepareVulnerabilitiesData($anr),
            'referentials' => $withControls ? $this->prepareReferentialsData($anr) : [],
            'informationRisks' => $this->prepareInformationRisksData($anr, $withEval),
            'tags' => $this->prepareTagsData($anr),
            'operationalRisks' => $this->prepareOperationalRisksData($anr),
            'recommendationSets' => $withRecommendations ? $this->prepareRecommendationSetsData($anr) : [],
        ];
    }

    private function prepareAssetsData(Entity\Anr $anr): array
    {
        $result = [];
        $languageIndex = $anr->getLanguage();
        /** @var Entity\Asset $asset */
        foreach ($this->assetTable->findByAnr($anr) as $asset) {
            $result[] = $this->prepareAssetData($asset, $languageIndex);
        }

        return $result;
    }

    private function prepareThreatsData(Entity\Anr $anr, bool $withEval): array
    {
        $result = [];
        $languageIndex = $anr->getLanguage();
        /** @var Entity\Threat $threat */
        foreach ($this->threatTable->findByAnr($anr) as $threat) {
            $result[] = $this->prepareThreatData($threat, $languageIndex, $withEval);
        }

        return $result;
    }

    private function prepareVulnerabilitiesData(Entity\Anr $anr): array
    {
        $result = [];
        $languageIndex = $anr->getLanguage();
        /** @var Entity\Vulnerability $vulnerability */
        foreach ($this->vulnerabilityTable->findByAnr($anr) as $vulnerability) {
            $result[] = $this->prepareVulnerabilityData($vulnerability, $languageIndex);
        }

        return $result;
    }

    private function prepareInformationRisksData(Entity\Anr $anr, bool $withEval): array
    {
        $result = [];
        /** @var Entity\Amv $amv */
        foreach ($this->amvTable->findByAnr($anr) as $amv) {
            $result[] = $this->prepareInformationRiskData($amv);
        }

        return $result;
    }

    private function prepareTagsData(Entity\Anr $anr): array
    {
        $result = [];
        $languageIndex = $anr->getLanguage();
        /** @var Entity\RolfTag $rolfTag */
        foreach ($this->rolfTagTable->findByAnr($anr) as $rolfTag) {
            $result[] = [
                'id' => $rolfTag->getId(),
                'code' => $rolfTag->getCode(),
                'label' => $rolfTag->getLabel($languageIndex),
            ];
        }

        return $result;
    }

    private function prepareOperationalRisksData(Entity\Anr $anr): array
    {
        $result = [];
        $languageIndex = $anr->getLanguage();
        /** @var Entity\RolfRisk $rolfRisk */
        foreach ($this->rolfRiskTable->findByAnr($anr) as $rolfRisk) {
            $result[] = $this->prepareOperationalRiskData($rolfRisk, $languageIndex);
        }

        return $result;
    }

    private function prepareReferentialsData(Entity\Anr $anr): array
    {
        $result = [];
        $languageIndex = $anr->getLanguage();
        /** @var Entity\Referential $referential */
        foreach ($this->referentialTable->findByAnr($anr) as $referential) {
            $measuresData = [];
            foreach ($referential->getMeasures() as $measure) {
                /* Include linked measures to the prepared result. */
                $measuresData[] = $this->prepareMeasureData($measure, $languageIndex, true);
            }

            $result[] = [
                'uuid' => $referential->getUuid(),
                'label' => $referential->getLabel($languageIndex),
                'measures' => $measuresData,
            ];
        }

        return $result;
    }

    private function prepareRecommendationSetsData(Entity\Anr $anr): array
    {
        $result = [];
        /** @var Entity\RecommendationSet $recommendationSet */
        foreach ($this->recommendationSetTable->findByAnr($anr) as $recommendationSet) {
            $recommendationsData = [];
            foreach ($recommendationSet->getRecommendations() as $recommendation) {
                $recommendationsData[] = $this->prepareRecommendationData($recommendation, false);
            }
            if (!empty($recommendationsData)) {
                $result[] = [
                    'uuid' => $recommendationSet->getUuid(),
                    'label' => $recommendationSet->getLabel(),
                    'recommendations' => $recommendationsData,
                ];
            }
        }

        return $result;
    }

    private function prepareLibraryData(Entity\Anr $anr): array
    {
        return [
            'categories' => $this->prepareCategoriesAndObjects($anr),
        ];
    }

    private function prepareCategoriesAndObjects(Entity\Anr $anr): array
    {
        $result = [];
        $languageIndex = $anr->getLanguage();
        foreach ($this->objectCategoryTable->findRootCategoriesByAnrOrderedByPosition($anr) as $objectCategory) {
            $result[] = $this->prepareCategoryData($objectCategory, $languageIndex, true);
        }

        return $result;
    }

    private function prepareChildrenCategoriesData(Entity\ObjectCategory $objectCategory, int $languageIndex): array
    {
        $result = [];
        foreach ($objectCategory->getChildren() as $childObjectCategory) {
            $result[] = $this
                ->prepareCategoryData($childObjectCategory, $languageIndex, false);
        }

        return $result;
    }

    private function prepareObjectsDataOfCategory(Entity\ObjectCategory $objectCategory, int $languageIndex): array
    {
        $result = [];
        foreach ($objectCategory->getObjects() as $object) {
            $result[] = $this->prepareObjectData($object, $languageIndex, false);
        }

        return $result;
    }

    private function prepareCategoryData(Entity\ObjectCategory $objectCategory, int $languageIndex, bool $isRoot): array
    {
        return [
            'label' => $objectCategory->getLabel($languageIndex),
            'children' => $this->prepareChildrenCategoriesData($objectCategory, $languageIndex),
            'objects' => $this->prepareObjectsDataOfCategory($objectCategory, $languageIndex),
            'isRoot' => $isRoot,
        ];
    }

    private function prepareInstancesData(
        Entity\Anr $anr,
        bool $includeObjectDataInTheResult,
        bool $withEval,
        bool $withControls,
        bool $withRecommendations
    ): array {
        $result = [];
        $languageIndex = $anr->getLanguage();
        /** @var Entity\Instance $instance */
        foreach ($this->instanceTable->findRootInstancesByAnrAndOrderByPosition($anr) as $instance) {
            $result[] = $this->prepareInstanceData(
                $instance,
                $languageIndex,
                $includeObjectDataInTheResult,
                false,
                $withEval,
                $withControls,
                $withRecommendations,
            );
        }

        return $result;
    }

    private function prepareScalesData(Entity\Anr $anr): array
    {
        $result = [];
        $languageIndex = $anr->getLanguage();
        /** @var Entity\Scale $scale */
        foreach ($this->scaleTable->findByAnr($anr) as $scale) {
            $result[$scale->getType()] = $this->prepareScaleData($scale, $languageIndex);
        }

        return $result;
    }

    private function prepareOperationalRiskScalesData(Entity\Anr $anr): array
    {
        $result = [];
        /** @var Entity\OperationalRiskScale $operationalRiskScale */
        foreach ($this->operationalRiskScaleTable->findByAnr($anr) as $operationalRiskScale) {
            $result[$operationalRiskScale->getType()] = $this->prepareOperationalRiskScaleData($operationalRiskScale);
        }

        return $result;
    }

    private function prepareSoaScaleCommentsData(Entity\Anr $anr): array
    {
        $result = [];
        /** @var Entity\SoaScaleComment $soaScaleComment */
        foreach ($this->soaScaleCommentTable->findByAnrOrderByIndex($anr) as $soaScaleComment) {
            if (!$soaScaleComment->isHidden()) {
                $result[] = [
                    'scaleIndex' => $soaScaleComment->getScaleIndex(),
                    'isHidden' => $soaScaleComment->isHidden(),
                    'colour' => $soaScaleComment->getColour(),
                    'comment' => $soaScaleComment->getComment(),
                ];
            }
        }

        return $result;
    }

    private function prepareSoasData(Entity\Anr $anr): array
    {
        $result = [];
        foreach ($this->soaTable->findByAnr($anr) as $soa) {
            $result = [
                'remarks' => $soa->getRemarks(),
                'evidences' => $soa->getEvidences(),
                'actions' => $soa->getActions(),
                'EX' => $soa->getEx(),
                'LR' => $soa->getLr(),
                'CO' => $soa->getCo(),
                'BR' => $soa->getBr(),
                'BP' => $soa->getBp(),
                'RRA' => $soa->getRra(),
                'soaScaleCommentId' => $soa->getSoaScaleComment()?->getId(),
                'measureUuid' => $soa->getMeasure()->getUuid(),
            ];
        }

        return $result;
    }

    private function prepareMethodData(Entity\Anr $anr): array
    {
        $languageIndex = $anr->getLanguage();
        $deliveriesData = [];
        /** @var Entity\Delivery $delivery */
        foreach ($this->deliveryTable->findByAnr($anr) as $delivery) {
            $docType = $delivery->getDocType();
            if (\in_array($docType, [
                Entity\Delivery::DOC_TYPE_CONTEXT_VALIDATION,
                Entity\Delivery::DOC_TYPE_MODEL_VALIDATION,
                Entity\Delivery::DOC_TYPE_FINAL_REPORT,
                Entity\Delivery::DOC_TYPE_IMPLEMENTATION_PLAN,
                Entity\Delivery::DOC_TYPE_SOA
            ], true)) {
                $deliveriesData[$docType] = [
                    'id' => $delivery->getId(),
                    'typedoc' => $docType,
                    'name' => $delivery->getName(),
                    'status' => $delivery->getStatus(),
                    'version' => $delivery->getVersion(),
                    'classification' => $delivery->getClassification(),
                    'respCustomer' => $delivery->getRespCustomer(),
                    'responsibleManager' => $delivery->getResponsibleManager(),
                    'summaryEvalRisk' => $delivery->getSummaryEvalRisk(),
                ];
            }
        }

        $questionsData = [];
        foreach ($this->questionTable->findByAnr($anr) as $question) {
            $questionChoicesData = [];
            foreach ($question->getQuestionChoices() as $questionChoice) {
                $questionChoicesData[] = [
                    'label' => $questionChoice->getLabel($languageIndex),
                    'position' => $questionChoice->getPosition(),
                ];
            }
            $questionPosition = $question->getPosition();
            $questionsData[$questionPosition] = [
                'id' => $question->getId(),
                'mode' => $question->getMode(),
                'isMultiChoice' => $question->isMultiChoice(),
                'label' => $question->getLabel($languageIndex),
                'response' => $question->getResponse(),
                'type' => $question->getType(),
                'position' => $questionPosition,
                'questionChoices' => $questionChoicesData,
            ];
        }
        ksort($questionsData);

        return [
            'steps' => [
                'initAnrContext' => $anr->getInitAnrContext(),
                'initEvalContext' => $anr->getInitEvalContext(),
                'initRiskContext' => $anr->getInitRiskContext(),
                'initDefContext' => $anr->getInitDefContext(),
                'modelImpacts' => $anr->getModelImpacts(),
                'modelSummary' => $anr->getModelSummary(),
                'evalRisks' => $anr->getEvalRisks(),
                'evalPlanRisks' => $anr->getEvalPlanRisks(),
                'manageRisks' => $anr->getManageRisks(),
            ],
            'data' => [
                'contextAnaRisk' => $anr->getContextAnaRisk(),
                'contextGestRisk' => $anr->getContextGestRisk(),
                'synthThreat' => $anr->getSynthThreat(),
                'synthAct' => $anr->getSynthAct(),
            ],
            'deliveries' => $deliveriesData,
            'questions' => $questionsData,
        ];
    }

    private function prepareAnrTrashholdsData(Entity\Anr $anr): array
    {
        return [
            'seuil1' => $anr->getSeuil1(),
            'seuil2' => $anr->getSeuil2(),
            'seuilRolf1' => $anr->getSeuilRolf1(),
            'seuilRolf2' => $anr->getSeuilRolf2(),
        ];
    }

    private function prepareInterviewsData(Entity\Anr $anr): array
    {
        $result = [];
        foreach ($this->interviewTable->findByAnr($anr) as $interview) {
            $result[] = [
                'id' => $interview->getId(),
                'date' => $interview->getDate(),
                'service' => $interview->getService(),
                'content' => $interview->getContent(),
            ];
        }

        return $result;
    }

    private function prepareGdprRecordsData(Entity\Anr $anr): array
    {
        $result = [];
        $filename = '';
        foreach ($this->recordTable->findByAnr($anr) as $record) {
            $result[] = $this->anrRecordService->generateExportArray($record->getId(), $filename);
        }

        return $result;
    }
}
