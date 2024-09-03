<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Exception\Exception;
use Monarc\Core\Entity\DeliveriesModels;
use Monarc\Core\Entity\InstanceRiskOpSuperClass;
use Monarc\Core\Entity\InstanceRiskSuperClass;
use Monarc\Core\Entity\OperationalRiskScaleSuperClass;
use Monarc\Core\Entity\ScaleImpactTypeSuperClass;
use Monarc\Core\Entity\ScaleSuperClass;
use Monarc\Core\Entity\UserSuperClass;
use Monarc\Core\Model\Table\DeliveriesModelsTable;
use Monarc\Core\Service as CoreService;
use Monarc\FrontOffice\Entity;
use Monarc\FrontOffice\Model\Table\RecordTable;
use Monarc\FrontOffice\Table;
use PhpOffice\PhpWord;
use function array_key_exists;
use function count;
use function in_array;

/**
 * The service handles generation of the deliverable Word documents throughout the steps of risk analysis.
 */
class DeliverableGenerationService
{
    private UserSuperClass $connectedUser;

    private int $currentLangAnrIndex = 1;

    private ?Entity\Anr $anr;

    private $noBorderTable;

    private $borderTable;
    private $whiteBigBorderTable;
    private $tblHeader;
    private $normalFont;

    private $boldFont;
    private $whiteFont;
    private $redFont;
    private $titleFont;
    private $centerParagraph;

    private $leftParagraph;
    private $verticalCenterParagraph;
    private $grayCell;

    private $blackCell;
    private $customizableCell;
    private $vAlignCenterCell;
    private $continueCell;
    private $colSpanCell;
    private $rotate90TextCell;
    private $restartAndGrayCell;
    private $continueAndGrayCell;
    private $restartAndBlackCell;
    private $continueAndBlackCell;
    private $restartAndCenterCell;
    private $restartAndTopCell;
    private $barChart;

    public function __construct(
        private Table\DeliveryTable $deliveryTable,
        private AnrInstanceConsequenceService $anrInstanceConsequenceService,
        private Table\InstanceTable $instanceTable,
        private Table\InstanceRiskTable $instanceRiskTable,
        private Table\InstanceRiskOpTable $instanceRiskOpTable,
        private Table\SoaScaleCommentTable $soaScaleCommentTable,
        private Table\RecommendationRiskTable $recommendationRiskTable,
        private Table\RecommendationHistoryTable $recommendationHistoryTable,
        private Table\AnrInstanceMetadataFieldTable $anrInstanceMetadataFieldTable,
        private Table\InstanceRiskOwnerTable $instanceRiskOwnerTable,
        private Table\ThreatTable $threatTable,
        private Table\ClientTable $clientTable,
        private Table\MeasureTable $measureTable,
        private RecordTable $recordTable,
        private DeliveriesModelsTable $deliveriesModelsTable,
        private OperationalRiskScaleService $operationalRiskScaleService,
        private AnrQuestionService $anrQuestionService,
        private AnrQuestionChoiceService $anrQuestionChoiceService,
        private AnrInterviewService $interviewService,
        private AnrCartoRiskService $cartoRiskService,
        private AnrInstanceRiskOpService $anrInstanceRiskOpService,
        private AnrInstanceRiskService $anrInstanceRiskService,
        private CoreService\Helper\ScalesCacheHelper $scalesCacheHelper,
        private CoreService\TranslateService $translateService,
        CoreService\ConnectedUserService $connectedUserService
    ) {
        $this->connectedUser = $connectedUserService->getConnectedUser();
    }

    /**
     * Retrieve the previous delivery for the specified type of document.
     */
    public function getLastDelivery(Entity\Anr $anr, int $docType): array
    {
        $lastDelivery = $this->deliveryTable->findLastByAnrAndDocType($anr, $docType);
        if ($lastDelivery === null) {
            return [];
        }

        return [
            'id' => $lastDelivery->getId(),
            'typedoc' => $lastDelivery->getDocType(),
            'name' => $lastDelivery->getName(),
            'version' => $lastDelivery->getVersion(),
            'status' => $lastDelivery->getStatus(),
            'classification' => $lastDelivery->getClassification(),
            'respCustomer' => $lastDelivery->getRespCustomer(),
            'respSmile' => $lastDelivery->getResponsibleManager(),
            'summaryEvalRisk' => $lastDelivery->getSummaryEvalRisk(),
        ];
    }

    /**
     * Generates the deliverable Word file.
     *
     * @return string The output file path.
     */
    public function generateDeliverableWithValues(Entity\Anr $anr, int $docType, array $data): string
    {
        $delivery = (new Entity\Delivery())
            ->setAnr($anr)
            ->setDocType($docType)
            ->setName($data['docname'] ?? '')
            ->setRespCustomer($data['consultants'] ?? '')
            ->setResponsibleManager($data['managers'] ?? '')
            ->setClassification($data['classification'] ?? '')
            ->setVersion($data['version'])
            ->setStatus((int)($data['status'] ?? 0))
            ->setSummaryEvalRisk($data['summaryEvalRisk'] ?? '')
            ->setCreator($this->connectedUser->getEmail());
        $this->deliveryTable->save($delivery);

        $this->anr = $anr;
        $this->currentLangAnrIndex = $anr->getLanguage();

        $deliveryModel = $this->deliveriesModelsTable->findById((int)$data['template']);

        $values = [
            'txt' => [
                'VERSION' => htmlspecialchars($delivery->getVersion()),
                'STATE' => $delivery->getStatus() === 0 ? 'Draft' : 'Final',
                'CLASSIFICATION' => htmlspecialchars($delivery->getClassification()),
                'COMPANY' => htmlspecialchars($this->clientTable->findFirstClient()->getName()),
                'DOCUMENT' => htmlspecialchars($delivery->getName()),
                'DATE' => date('d/m/Y'),
                'CLIENT' => htmlspecialchars($delivery->getResponsibleManager()),
                'SMILE' => htmlspecialchars($delivery->getRespCustomer()),
                'SUMMARY_EVAL_RISK' => $this->generateWordXmlFromHtml(_WT($delivery->getSummaryEvalRisk())),
            ],
        ];

        $pathModel = (getenv('APP_CONF_DIR') ?: '') . $deliveryModel->getPath($this->currentLangAnrIndex);
        if (!file_exists($pathModel)) {
            /* if template not available in the language of the ANR, use the default template of the category. */
            $pathModel = getenv('APP_CONF_DIR') ?? '';
            $deliveryModel = current(
                $this->deliveriesModelsTable->getEntityByFields([
                    'category' => $docType,
                    'path2' => ['op' => 'IS NOT', 'value' => null],
                ])
            );
            $pathModel .= $deliveryModel->path2;
            if (!file_exists($pathModel)) {
                throw new Exception('Model not found "' . $pathModel . '"');
            }
        }

        $referentialUuid = $data['referential'] ?? null;
        $risksByControl = $data['risksByControl'] ?? false;
        $record = $data['record'] ?? null;

        $values = array_merge_recursive(
            $values,
            $this->buildValues($docType, $referentialUuid, $record, $risksByControl)
        );

        return $this->generateDeliverableWithValuesAndModel($pathModel, $values);
    }

    /**
     * Translates the provided input text into the current ANR language
     *
     * @param string $text The text to translate
     *
     * @return string THe translated text, or $text if no translation was found
     */
    private function anrTranslate(string $text): string
    {
        return $this->translateService->translate($text, $this->currentLangAnrIndex);
    }

    /**
     * Method called by generateDeliverableWithValues to generate the model from its path and values.
     *
     * @param string $modelPath The file path to the DOCX model to use
     * @param array $values The values to fill in the document
     *
     * @return string The path to the generated temporary document.
     */
    private function generateDeliverableWithValuesAndModel(string $modelPath, array $values): string
    {
        /* Verify the template existence. */
        if (!file_exists($modelPath)) {
            throw new Exception("Model path not found: " . $modelPath);
        }

        $word = new PhpWord\TemplateProcessor($modelPath);

        if (!empty($values['txt'])) {
            foreach ($values['txt'] as $key => $value) {
                $word->setValue($key, $value);
            }
        }
        if (!empty($values['table'])) {
            foreach ($values['table'] as $key => $value) {
                $word->setComplexBlock($key, $value);
            }
        }
        if (!empty($values['xml'])) {
            foreach ($values['xml'] as $key => $value) {
                $word->replaceXmlBlock($key, $value, 'w:p');
            }
        }
        if (!empty($values['chart'])) {
            foreach ($values['chart'] as $key => $value) {
                if (isset($value)) {
                    $word->setChart($key, $value);
                }
            }
        }

        return $word->save();
    }

    /**
     * Builds the values to fill in the word document.
     *
     * @param int $modelCategory The model type
     *
     * @return array The values for the Word document as a key-value array
     */
    private function buildValues(
        $modelCategory,
        ?string $referentialUuid = null,
        $record = null,
        $risksByControl = false
    ): array {
        $this->setStyles();

        return match ($modelCategory) {
            DeliveriesModels::MODEL_CONTEXT_VALIDATION => $this->buildContextValidationValues(),
            DeliveriesModels::MODEL_ASSETS_AND_MODELS_VALIDATION => $this->buildContextModelingValues(),
            DeliveriesModels::MODEL_RISK_ANALYSIS => $this->buildRiskAssessmentValues(),
            DeliveriesModels::MODEL_IMPLEMENTATION_PLAN => $this->buildImplementationPlanValues(),
            DeliveriesModels::MODEL_STATEMENT_OF_APPLICABILITY => $referentialUuid
                ? $this->buildStatementOfAppplicabilityValues($referentialUuid, $risksByControl)
                : [],
            DeliveriesModels::MODEL_RECORD_OF_PROCESSING_ACTIVITIES => $this
                ->buildRecordOfProcessingActivitiesValues($record),
            DeliveriesModels::MODEL_ALL_RECORD_OF_PROCESSING_ACTIVITIES => $this->buildAllRecordsValues(),
            default => [],
        };
    }

    /**
     * Set table styles
     */
    private function setStyles()
    {
        //Table Style
        $this->noBorderTable = ['align' => 'center', 'cellMarginRight' => '0'];
        $this->borderTable = array_merge($this->noBorderTable, ['borderSize' => 1, 'borderColor' => 'ABABAB']);
        $this->whiteBigBorderTable = ['valign' => 'center', 'borderSize' => 20, 'borderColor' => 'FFFFFF'];
        $this->tblHeader = ['tblHeader' => true];

        //Font Style
        $this->normalFont = ['size' => 10];
        $this->boldFont = array_merge(['bold' => true], $this->normalFont);
        $this->whiteFont = array_merge($this->normalFont, ['color' => 'FFFFFF']);
        $this->redFont = ['bold' => true, 'color' => 'FF0000', 'size' => 12];
        $this->titleFont = array_merge($this->boldFont, ['size' => 12]);

        //Paragraph style
        $this->centerParagraph = ['alignment' => 'center', 'spaceAfter' => '0'];
        $this->leftParagraph = ['alignment' => 'left', 'spaceAfter' => '0'];
        $this->verticalCenterParagraph = ['alignment' => 'center'];

        //Cell style
        $this->grayCell = ['valign' => 'center', 'bgcolor' => 'DFDFDF'];
        $this->blackCell = ['valign' => 'center', 'bgcolor' => '444444'];
        $this->customizableCell = ['valign' => 'center'];
        $this->vAlignCenterCell = ['valign' => 'center'];
        $this->continueCell = ['vMerge' => 'continue'];
        $this->colSpanCell = $this->vAlignCenterCell;
        $this->rotate90TextCell = array_merge(
            $this->vAlignCenterCell,
            ['vMerge' => 'restart', 'textDirection' => 'btLr']
        );
        $this->restartAndGrayCell = array_merge($this->grayCell, ['vMerge' => 'restart']);
        $this->continueAndGrayCell = array_merge($this->continueCell, $this->grayCell);
        $this->restartAndBlackCell = array_merge($this->blackCell, ['vMerge' => 'restart']);
        $this->continueAndBlackCell = array_merge($this->continueCell, $this->blackCell);
        $this->restartAndCenterCell = array_merge($this->vAlignCenterCell, ['vMerge' => 'restart']);
        $this->restartAndTopCell = ['vMerge' => 'restart', 'valign' => 'top'];

        //Chart styles
        $this->barChart = [
            'width' => PhpWord\Shared\Converter::cmToEmu(17),
            'height' => PhpWord\Shared\Converter::cmToEmu(9.5),
            'dataLabelOptions' => ['showCatName' => false],
            'colors' => ['D6F107', 'FFBC1C', 'FD661F'],
            'showAxisLabels' => true,
            'showGridY' => true,
        ];
    }

    /**
     * Set Span and Color Cell
     *
     * @param int $nCol number of columns
     * @param string $color HEX color
     *
     * @return array $this->colSpanCell
     */
    private function setColSpanCell($nCol, $color = null): array
    {
        $this->colSpanCell['gridSpan'] = $nCol;
        $this->colSpanCell['bgcolor'] = $color;

        return $this->colSpanCell;
    }

    /**
     * Set bgColor by thresholds value.
     *
     * @param int|string $nCol number of columns
     * @param string $color HEX color
     *
     * @return array $this->colSpanCell
     */
    private function setBgColorCell($value, bool $infoRisk = true): array
    {

        if ($infoRisk) {
            $thresholds = [
                'low' => $this->anr->getSeuil1(),
                'high' => $this->anr->getSeuil2(),
            ];
        } else {
            $thresholds = [
                'low' => $this->anr->getSeuilRolf1(),
                'high' => $this->anr->getSeuilRolf2(),
            ];
        }

        if ($value === null) {
            $this->customizableCell['BgColor'] = 'E7E6E6';

            return $this->customizableCell;
        }

        $this->customizableCell['BgColor'] = 'FD661F';
        if ($value === '-') {
            $this->customizableCell['BgColor'] = '';
        } elseif ($value <= $thresholds['low']) {
            $this->customizableCell['BgColor'] = 'D6F107';
        } elseif ($value <= $thresholds['high']) {
            $this->customizableCell['BgColor'] = 'FFBC1C';
        }

        return $this->customizableCell;
    }

    /**
     * Build values for Step 1 deliverable (context validation).
     */
    private function buildContextValidationValues(): array
    {
        /**
         * @var Entity\Scale $impactsScale
         * @var Entity\Scale $threatsScale
         * @var Entity\Scale $vulnsScale
         */
        $impactsScale = $this->scalesCacheHelper->getCachedScaleByType($this->anr, ScaleSuperClass::TYPE_IMPACT);
        $threatsScale = $this->scalesCacheHelper->getCachedScaleByType($this->anr, ScaleSuperClass::TYPE_THREAT);
        $vulnsScale = $this->scalesCacheHelper->getCachedScaleByType($this->anr, ScaleSuperClass::TYPE_VULNERABILITY);

        $opRisksAllScales = $this->operationalRiskScaleService->getOperationalRiskScales($this->anr);
        $opRisksImpactsScaleType = array_values(array_filter($opRisksAllScales, static function ($scale) {
            return $scale['type'] === OperationalRiskScaleSuperClass::TYPE_IMPACT;
        }));
        $opRisksImpactsScaleMin = $opRisksImpactsScaleType[0]['min'];
        $opRisksImpactsScaleMax = $opRisksImpactsScaleType[0]['max'];
        $opRisksImpactsScales = array_values(
            array_filter($opRisksImpactsScaleType[0]['scaleTypes'], static function ($scale) {
                return !$scale['isHidden'];
            })
        );
        $opRisksLikelihoodScale = array_values(array_filter($opRisksAllScales, static function ($scale) {
            return $scale['type'] === OperationalRiskScaleSuperClass::TYPE_LIKELIHOOD;
        }))[0];

        return [
            'xml' => [
                'CONTEXT_ANA_RISK' => $this->generateWordXmlFromHtml(_WT($this->anr->getContextAnaRisk())),
                'CONTEXT_GEST_RISK' => $this->generateWordXmlFromHtml(_WT($this->anr->getContextGestRisk())),
                'SYNTH_EVAL_THREAT' => $this->generateWordXmlFromHtml(_WT($this->anr->getSynthThreat())),
            ],
            'table' => [
                'SCALE_IMPACT' => $this->generateInformationalRiskImpactsTable($impactsScale),
                'SCALE_THREAT' => $this->generateThreatOrVulnerabilityScaleTable($threatsScale),
                'SCALE_VULN' => $this->generateThreatOrVulnerabilityScaleTable($vulnsScale),
                'TABLE_RISKS' => $this->generateInformationalRiskAcceptanceThresholdsTable(
                    $impactsScale,
                    $threatsScale,
                    $vulnsScale
                ),
                'OP_RISKS_SCALE_IMPACT' => $this->generateOperationalRiskImpactsTable(
                    $opRisksImpactsScales,
                    $opRisksImpactsScaleMin,
                    $opRisksImpactsScaleMax
                ),
                'OP_RISKS_SCALE_LIKELIHOOD' => $this->generateOperationalRiskLikelihoodTable(
                    $opRisksLikelihoodScale
                ),
                'TABLE_OP_RISKS' => $this->generateOperationalRiskAcceptanceThresholdsTable(
                    $opRisksImpactsScales,
                    $opRisksLikelihoodScale,
                    $opRisksImpactsScaleMin,
                    $opRisksImpactsScaleMax
                ),
                'TABLE_THREATS' => $this->generateThreatsTable(),
                'TABLE_EVAL_TEND' => $this->generateTrendAssessmentTable(),
                'TABLE_THREATS_FULL' => $this->generateThreatsTable(true),
                'TABLE_INTERVIEW' => $this->generateInterviewsTable(),
            ],
        ];
    }

    /**
     * Build values for Step 2 deliverable (context modeling)
     * @return array The key-value array
     */
    private function buildContextModelingValues()
    {
        // Models are incremental, so use values from level-1 model
        $values = $this->buildContextValidationValues();

        $values['xml']['SYNTH_ACTIF'] = $this->generateWordXmlFromHtml(_WT($this->anr->getSynthAct()));
        $values['table']['IMPACTS_APPRECIATION'] = $this->generateImpactsAppreciation();

        return $values;
    }

    /**
     * Build values for Step 3 deliverable (risk assessment)
     * @return array The key-value array
     */
    private function buildRiskAssessmentValues()
    {
        // Models are incremental, so use values from level-2 model
        $values = $this->buildContextModelingValues();

        $values = array_merge_recursive($values, [
            'chart' => [
                'GRAPH_EVAL_RISK' => $this->generateRisksGraph(),
                'GRAPH_EVAL_OP_RISK' => $this->generateRisksGraph(false),
            ]
        ]);

        $values = array_merge_recursive($values, [
            'table' => [
                'RISKS_RECO_FULL' => $this->generateRisksPlan(),
                'OPRISKS_RECO_FULL' => $this->generateOperationalRisksPlan(),
                'TABLE_RISK_OWNERS' => $this->generateOwnersTable(),
            ]
        ]);

        $values = array_merge_recursive(
            $values,
            [
                'xml' => [
                    'DISTRIB_EVAL_RISK' => $this->generateWordXmlFromHtml(_WT($this->getRisksDistribution())),
                    'DISTRIB_EVAL_OP_RISK' => $this->generateWordXmlFromHtml(_WT($this->getRisksDistribution(false))),
                    'CURRENT_RISK_MAP' => $this->generateCurrentRiskMap(),
                    'TARGET_RISK_MAP' => $this->generateCurrentRiskMap('targeted'),
                    'TABLE_ASSET_CONTEXT' => $this->generateAssetContextTable(),
                    'RISKS_KIND_OF_TREATMENT' => $this->generateRisksByKindOfMeasure(),
                    'TABLE_AUDIT_INSTANCES' => $this->generateTableAudit(),
                    'TABLE_AUDIT_RISKS_OP' => $this->generateTableAuditOp(),
                ],
            ]
        );

        return $values;
    }

    /**
     * Build values for Step 4 deliverable (Implementation plan)
     * @return array The key-value array
     */
    private function buildImplementationPlanValues()
    {
        $values = [
            'table' => [
                'TABLE_IMPLEMENTATION_PLAN' => $this->generateTableImplementationPlan(),
                'TABLE_IMPLEMENTATION_HISTORY' => $this->generateTableImplementationHistory(),
            ],
        ];

        return $values;
    }

    /**
     * Build values for Step 5 deliverable (Statement Of Applicability)
     * @return array The key-value array
     */
    private function buildStatementOfAppplicabilityValues(string $referentialUuid, $risksByControl)
    {
        /** @var Entity\SoaScaleComment[] $soaScaleComments */
        $soaScaleComments = $this->soaScaleCommentTable->findByAnrOrderByIndex($this->anr, true);
        $values = [
            'table' => [
                'TABLE_STATEMENT_OF_APPLICABILITY_SCALE' => $this->generateTableStatementOfApplicabilityScale(
                    $soaScaleComments
                ),
                'TABLE_STATEMENT_OF_APPLICABILITY' => $this->generateTableStatementOfApplicability($referentialUuid),
            ],
        ];
        if ($risksByControl) {
            $values['xml']['TABLE_RISKS_BY_CONTROL'] = $this->generateTableRisksByControl($referentialUuid);
        } else {
            $values['txt']['TABLE_RISKS_BY_CONTROL'] = null;
        }

        return $values;
    }

    /**
     * Build values for Step 5 deliverable (Record of Processing Activities)
     * @return array The key-value array
     */
    private function buildRecordOfProcessingActivitiesValues($record)
    {
        $values = [
            'xml' => [
                'TABLE_RECORD_INFORMATION' => $this->generateTableRecordGDPR($record),
                'TABLE_RECORD_ACTORS' => $this->generateTableRecordActors($record),
                'TABLE_RECORD_PERSONAL_DATA' => $this->generateTableRecordPersonalData($record),
                'TABLE_RECORD_RECIPIENTS' => $this->generateTableRecordRecipients($record),
                'TABLE_RECORD_INTERNATIONAL_TRANSFERS' => $this->generateTableRecordInternationalTransfers($record),
                'TABLE_RECORD_PROCESSORS' => $this->generateTableRecordProcessors($record),
            ],
        ];

        return $values;
    }

    /**
     * Build values for Step 5 deliverable (All Records of Processing Activities)
     * @return array The key-value array
     */
    private function buildAllRecordsValues()
    {
        $values['xml']['TABLE_ALL_RECORDS'] = $this->generateTableAllRecordsGDPR();

        return $values;
    }

    /**
     * Generate Informational Risk Impacts table.
     */
    private function generateInformationalRiskImpactsTable(Entity\Scale $impactScale): PhpWord\Element\Table
    {
        $tableWord = new PhpWord\PhpWord();
        $section = $tableWord->addSection();
        $table = $section->addTable($this->borderTable);

        $table->addRow(400, $this->tblHeader);
        $table->addCell(PhpWord\Shared\Converter::cmToTwip(2.00), $this->restartAndGrayCell)
            ->addText($this->anrTranslate('Level'), $this->boldFont, $this->centerParagraph);
        $table->addCell(PhpWord\Shared\Converter::cmToTwip(8.40), $this->setColSpanCell(3, 'DFDFDF'))
            ->addText($this->anrTranslate('Impact'), $this->boldFont, $this->centerParagraph);
        $table->addCell(PhpWord\Shared\Converter::cmToTwip(8.60), $this->restartAndGrayCell)
            ->addText($this->anrTranslate('Consequences'), $this->boldFont, $this->centerParagraph);

        // Manually add C/I/D impacts columns
        $table->addRow();
        $table->addCell(PhpWord\Shared\Converter::cmToTwip(2.00), $this->continueAndGrayCell);
        $table->addCell(PhpWord\Shared\Converter::cmToTwip(2.80), $this->grayCell)
            ->addText($this->anrTranslate('Confidentiality'), $this->boldFont, $this->centerParagraph);
        $table->addCell(PhpWord\Shared\Converter::cmToTwip(2.80), $this->grayCell)
            ->addText($this->anrTranslate('Integrity'), $this->boldFont, $this->centerParagraph);
        $table->addCell(PhpWord\Shared\Converter::cmToTwip(2.80), $this->grayCell)
            ->addText($this->anrTranslate('Availability'), $this->boldFont, $this->centerParagraph);
        $table->addCell(PhpWord\Shared\Converter::cmToTwip(8.60), $this->continueAndGrayCell);

        // Put C/I/D first
        $scaleImpactTypesPerType = [];
        foreach ($impactScale->getScaleImpactTypes() as $scaleImpactType) {
            if (!$scaleImpactType->isHidden()) {
                $scaleImpactTypesPerType[$scaleImpactType->getType()] = $scaleImpactType;
            }
        }
        ksort($scaleImpactTypesPerType);

        // Fill in each row
        for ($scaleIndex = $impactScale->getMin(); $scaleIndex <= $impactScale->getMax(); ++$scaleIndex) {
            $table->addRow(400);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(2.00), $this->restartAndTopCell)
                ->addText((string)$scaleIndex, $this->normalFont, $this->centerParagraph);

            $first = true;
            foreach ($scaleImpactTypesPerType as $type => $scaleImpactType) {
                $commentText = '';
                foreach ($scaleImpactType->getScaleComments() as $scaleComment) {
                    if ($scaleComment->getScaleIndex() === $scaleIndex) {
                        $commentText = $scaleComment->getComment($this->currentLangAnrIndex);
                        break;
                    }
                }
                if (\in_array($type, ScaleImpactTypeSuperClass::getScaleImpactTypesCid(), true)) {
                    $table->addCell(PhpWord\Shared\Converter::cmToTwip(2.80), $this->restartAndTopCell)
                        ->addText(_WT($commentText), $this->normalFont, $this->leftParagraph);
                } else {
                    // Then ROLFP and custom columns as rows
                    if (!$first) {
                        $table->addRow(400);
                        $table->addCell(PhpWord\Shared\Converter::cmToTwip(2.15), $this->continueCell);
                        $table->addCell(PhpWord\Shared\Converter::cmToTwip(2.15), $this->continueCell);
                        $table->addCell(PhpWord\Shared\Converter::cmToTwip(2.15), $this->continueCell);
                        $table->addCell(PhpWord\Shared\Converter::cmToTwip(2.15), $this->continueCell);
                    }
                    $cellConsequences = $table
                        ->addCell(PhpWord\Shared\Converter::cmToTwip(2.80), $this->vAlignCenterCell);
                    $cellConsequencesRun = $cellConsequences->addTextRun($this->leftParagraph);
                    $cellConsequencesRun->addText(
                        _WT($this->anrTranslate($scaleImpactType->getLabel($this->currentLangAnrIndex))) . ' : ',
                        $this->boldFont
                    );
                    $cellConsequencesRun->addText(_WT($commentText), $this->normalFont);

                    $first = false;
                }
            }
        }

        return $table;
    }

    /**
     * Generate Informational Risk Acceptance thresholds table.
     */
    private function generateInformationalRiskAcceptanceThresholdsTable(
        Entity\Scale $impactsScale,
        Entity\Scale $threatsScale,
        Entity\Scale $vulnsScale
    ): PhpWord\Element\Table {
        $tableWord = new PhpWord\PhpWord();
        $section = $tableWord->addSection();
        $table = $section->addTable($this->noBorderTable);

        $header = [];
        for ($t = $threatsScale->getMin(); $t <= $threatsScale->getMax(); ++$t) {
            for ($v = $vulnsScale->getMin(); $v <= $vulnsScale->getMax(); ++$v) {
                $prod = $t * $v;
                if (!\in_array($prod, $header, true)) {
                    $header[] = $prod;
                }
            }
        }
        asort($header);

        $size = 13 / (count($header) + 2); // 15cm
        $table->addRow();
        $table->addCell(null, $this->setColSpanCell(2));
        $table->addCell(null, $this->setColSpanCell(count($header)))
            ->addText($this->anrTranslate('TxV'), $this->boldFont, $this->centerParagraph);
        $table->addRow();
        $table->addCell(null, $this->rotate90TextCell)
            ->addText($this->anrTranslate('Impact'), $this->boldFont, $this->centerParagraph);
        $table->addCell(null, $this->whiteBigBorderTable);
        foreach ($header as $MxV) {
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(1), $this->whiteBigBorderTable)
                ->addText($MxV, $this->boldFont, $this->centerParagraph);
        }

        for ($row = $impactsScale->getMin(); $row <= $impactsScale->getMax(); ++$row) {
            $table->addRow(PhpWord\Shared\Converter::cmToTwip($size));
            $table->addCell(null, $this->continueCell);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(1), $this->whiteBigBorderTable)
                ->addText((string)$row, $this->boldFont, $this->centerParagraph);

            foreach ($header as $MxV) {
                $value = $MxV * $row;

                $style = array_merge($this->whiteBigBorderTable, $this->setBgColorCell($value));
                $table->addCell(null, $style)->addText((string)$value, $this->boldFont, $this->centerParagraph);
            }
        }

        return $table;
    }

    /**
     * Generate Operational Risk Acceptance thresholds Table
     */
    private function generateOperationalRiskImpactsTable(
        $opRisksImpactsScales,
        $opRisksImpactsScaleMin,
        $opRisksImpactsScaleMax
    ): PhpWord\Element\Table {
        $tableWord = new PhpWord\PhpWord();
        $section = $tableWord->addSection();
        $table = $section->addTable($this->borderTable);

        $sizeColumn = 17 / count($opRisksImpactsScales);

        $table->addRow(400, $this->tblHeader);
        $table->addCell(PhpWord\Shared\Converter::cmToTwip(2.00), $this->grayCell)
            ->addText($this->anrTranslate('Level'), $this->boldFont, $this->centerParagraph);
        foreach ($opRisksImpactsScales as $opRiskImpactScale) {
            $table->addCell(PhpWord\Shared\Converter::cmToTwip($sizeColumn), $this->grayCell)
                ->addText(_WT($opRiskImpactScale['label']), $this->boldFont, $this->centerParagraph);
        }

        for ($row = $opRisksImpactsScaleMin; $row <= $opRisksImpactsScaleMax; ++$row) {
            $table->addRow(400);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(2.00), $this->restartAndTopCell)->addText(
                $opRisksImpactsScales[0]['comments'][$row]['scaleValue'],
                $this->normalFont,
                $this->centerParagraph
            );
            foreach ($opRisksImpactsScales as $opRiskImpactScale) {
                $table->addCell(PhpWord\Shared\Converter::cmToTwip($sizeColumn), $this->restartAndTopCell)->addText(
                    _WT($opRiskImpactScale['comments'][$row]['comment']),
                    $this->normalFont,
                    $this->leftParagraph
                );
            }
        }

        return $table;
    }

    /**
     * Generate Operational Risk Likelihood Table.
     */
    private function generateOperationalRiskLikelihoodTable($opRisksLikelihoodScale): PhpWord\Element\Table
    {
        $tableWord = new PhpWord\PhpWord();
        $section = $tableWord->addSection();
        $table = $section->addTable($this->borderTable);

        $table->addRow(400, $this->tblHeader);
        $table->addCell(PhpWord\Shared\Converter::cmToTwip(2.00), $this->grayCell)
            ->addText($this->anrTranslate('Level'), $this->boldFont, $this->centerParagraph);
        $table->addCell(PhpWord\Shared\Converter::cmToTwip(16.00), $this->grayCell)
            ->addText($this->anrTranslate('Comment'), $this->boldFont, $this->centerParagraph);

        foreach ($opRisksLikelihoodScale['comments'] as $comment) {
            $table->addRow(400);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(2.00), $this->vAlignCenterCell)
                ->addText($comment['scaleValue'], $this->normalFont, $this->centerParagraph);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(16.00), $this->vAlignCenterCell)
                ->addText(_WT($comment['comment']), $this->normalFont, $this->leftParagraph);
        }

        return $table;
    }

    /**
     * Generate Operational Risk Acceptance thresholds table.
     */
    private function generateOperationalRiskAcceptanceThresholdsTable(
        $opRisksImpactsScales,
        $opRisksLikelihoodScale,
        $opRisksImpactsScaleMin,
        $opRisksImpactsScaleMax
    ): PhpWord\Element\Table {
        $tableWord = new PhpWord\PhpWord();
        $section = $tableWord->addSection();
        $table = $section->addTable($this->noBorderTable);

        $header = [];
        for ($t = $opRisksLikelihoodScale['min']; $t <= $opRisksLikelihoodScale['max']; ++$t) {
            $header[] = $t;
        }
        asort($header);

        $size = 0.87;
        $table->addRow();
        $table->addCell(null, $this->setColSpanCell(2));
        $table->addCell(null, $this->setColSpanCell(count($header)))
            ->addText($this->anrTranslate('Probability'), $this->boldFont, $this->centerParagraph);
        $table->addRow();
        $table->addCell(null, $this->rotate90TextCell)
            ->addText($this->anrTranslate('Impact'), $this->boldFont, $this->centerParagraph);
        $table->addCell(null, $this->whiteBigBorderTable);
        foreach ($header as $prob) {
            $table->addCell(PhpWord\Shared\Converter::cmToTwip($size), $this->whiteBigBorderTable)
                ->addText($prob, $this->boldFont, $this->centerParagraph);
        }

        for ($row = $opRisksImpactsScaleMin; $row <= $opRisksImpactsScaleMax; ++$row) {
            $impactValue = $opRisksImpactsScales[0]['comments'][$row]['scaleValue'];
            $table->addRow(PhpWord\Shared\Converter::cmToTwip($size));
            $table->addCell(null, $this->continueCell);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip($size), $this->whiteBigBorderTable)
                ->addText($impactValue, $this->boldFont, $this->centerParagraph);
            foreach ($header as $prob) {
                $value = $prob * $impactValue;
                $style = array_merge($this->whiteBigBorderTable, $this->setBgColorCell($value, false));
                $table->addCell(null, $style)->addText((string)$value, $this->boldFont, $this->centerParagraph);
            }
        }

        return $table;
    }

    /**
     * Generate Trends Assessment Table.
     */
    private function generateTrendAssessmentTable(): PhpWord\Element\Table
    {
        $questions = $this->anrQuestionService->getList(1, 0, null, null, ['anr' => $this->anr->getId()]);
        $questionsChoices = $this->anrQuestionChoiceService->getList(1, 0, null, null, ['anr' => $this->anr->getId()]);

        $tableWord = new PhpWord\PhpWord();
        $section = $tableWord->addSection();
        $table = $section->addTable($this->noBorderTable);

        // Fill in each row
        foreach ($questions as $question) {
            $response = '';
            if ($question['type'] === 1) {
                // Simple text
                $response = $question['response'];
            } else {
                // Choice, either simple or multiple
                if ($question['multichoice']) {
                    $responseIds = empty($question['response']) ? [] : json_decode($question['response'], true);
                    $responses = [];

                    if (!empty($responseIds)) {
                        foreach ($questionsChoices as $choice) {
                            if (in_array($choice['id'], $responseIds, true)) {
                                $responses[] = '- ' . $choice['label' . $this->currentLangAnrIndex];
                            }
                        }
                        $response = implode("\n", $responses);
                    }
                } else {
                    foreach ($questionsChoices as $choice) {
                        if ($choice['id'] === $question['response']) {
                            $response = $choice['label' . $this->currentLangAnrIndex];
                            break;
                        }
                    }
                }
            }

            // no display question, if reply is empty
            if (!empty($response)) {
                $table->addRow(400);
                $table->addCell(PhpWord\Shared\Converter::cmToTwip(18.00), $this->vAlignCenterCell)
                    ->addText(
                        _WT($question['label' . $this->currentLangAnrIndex]),
                        $this->boldFont,
                        $this->leftParagraph
                    );
                $table->addRow(400);
                $table->addCell(PhpWord\Shared\Converter::cmToTwip(18.00), $this->vAlignCenterCell)
                    ->addText(_WT($response), $this->normalFont, $this->leftParagraph);
            }
        }

        return $table;
    }

    /**
     * Generate Interviews Table.
     */
    private function generateInterviewsTable(): PhpWord\Element\Table
    {
        $interviews = $this->interviewService->getList(1, 0, null, null, ['anr' => $this->anr->getId()]);

        $tableWord = new PhpWord\PhpWord();
        $section = $tableWord->addSection();
        $table = $section->addTable($this->borderTable);

        if (count($interviews)) {
            $table->addRow(400, $this->tblHeader);

            $table->addCell(PhpWord\Shared\Converter::cmToTwip(4.00), $this->grayCell)
                ->addText($this->anrTranslate("Date"), $this->boldFont, $this->centerParagraph);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(4.00), $this->grayCell)
                ->addText($this->anrTranslate("Department / People"), $this->boldFont, $this->centerParagraph);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(9.00), $this->grayCell)
                ->addText($this->anrTranslate("Contents"), $this->boldFont, $this->centerParagraph);
        }

        // Fill in each row
        foreach ($interviews as $interview) {
            $table->addRow(400);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(4.00), $this->vAlignCenterCell)
                ->addText(_WT($interview['date']), $this->normalFont, $this->leftParagraph);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(4.00), $this->vAlignCenterCell)
                ->addText(_WT($interview['service']), $this->normalFont, $this->leftParagraph);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(9.00), $this->vAlignCenterCell)
                ->addText(_WT($interview['content']), $this->normalFont, $this->leftParagraph);
        }

        return $table;
    }

    /**
     * Generate Threat or Vulnerability scale table.
     */
    private function generateThreatOrVulnerabilityScaleTable(Entity\Scale $scale): PhpWord\Element\Table
    {
        $tableWord = new PhpWord\PhpWord();
        $section = $tableWord->addSection();
        $table = $section->addTable($this->borderTable);

        $table->addRow(400, $this->tblHeader);
        $table->addCell(PhpWord\Shared\Converter::cmToTwip(2.00), $this->grayCell)
            ->addText($this->anrTranslate('Level'), $this->boldFont, $this->centerParagraph);
        $table->addCell(PhpWord\Shared\Converter::cmToTwip(16.00), $this->grayCell)
            ->addText($this->anrTranslate('Comment'), $this->boldFont, $this->centerParagraph);

        // Fill in each row
        for ($scaleIndex = $scale->getMin(); $scaleIndex <= $scale->getMax(); ++$scaleIndex) {
            $table->addRow(400);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(2.00), $this->vAlignCenterCell)
                ->addText($scaleIndex, $this->normalFont, $this->centerParagraph);

            // Find the appropriate comment
            $commentText = '';
            foreach ($scale->getScaleComments() as $scaleComment) {
                if ($scaleComment->getScaleIndex() === $scaleIndex) {
                    $commentText = $scaleComment->getComment($this->currentLangAnrIndex);
                    break;
                }
            }

            $table->addCell(PhpWord\Shared\Converter::cmToTwip(16.00), $this->vAlignCenterCell)
                ->addText(_WT($commentText), $this->normalFont, $this->leftParagraph);
        }

        return $table;
    }

    /**
     * Generate Current Risk Map.
     */
    private function generateCurrentRiskMap(string $type = 'real'): string
    {
        $cartoRisk = $type === 'real'
            ? $this->cartoRiskService->getCartoReal($this->anr)
            : $this->cartoRiskService->getCartoTargeted($this->anr);

        // Generate risks table
        $tableWord = new PhpWord\PhpWord();
        $section = $tableWord->addSection();

        if (!empty($cartoRisk['riskInfo']['counters'])) {
            $section->addText($this->anrTranslate('Information risks'), $this->boldFont, ['indent' => 0.5]);

            $params = [
                'riskType' => 'riskInfo',
                'axisX' => 'MxV',
                'axisY' => 'Impact',
                'labelAxisX' => 'TxV',
                'thresholds' => [
                    $this->anr->getSeuil1(),
                    $this->anr->getSeuil2(),
                ],
            ];
            $section = $this->generateCartographyMap($cartoRisk, $section, $params);
        }
        if (!empty($cartoRisk['riskOp']['counters'])) {
            $section->addText(
                $this->anrTranslate('Operational risks'),
                $this->boldFont,
                ['indent' => 0.5]
            );
            $params = [
                'riskType' => 'riskOp',
                'axisX' => 'Likelihood',
                'axisY' => 'OpRiskImpact',
                'labelAxisX' => 'Probability',
                'thresholds' => [
                    $this->anr->getSeuilRolf1(),
                    $this->anr->getSeuilRolf2(),
                ],
            ];
            $this->generateCartographyMap($cartoRisk, $section, $params);
        }

        return $this->getWordXmlFromWordObject($tableWord);
    }

    /**
     * Generate Cartography Map
     *
     * @param $data
     * @param object $section
     * @param array $params
     *
     * @return object
     */
    private function generateCartographyMap($data, $section, $params)
    {
        $axisX = $data[$params['axisX']];
        $axisY = $data[$params['axisY']];
        $labelAxisX = $params['labelAxisX'];
        $data = $data[$params['riskType']]['counters'];
        $thresholds = $params['thresholds'];
        $size = 0.75;

        $table = $section->addTable($this->noBorderTable);
        $table->addRow(PhpWord\Shared\Converter::cmToTwip($size));
        $table->addCell(null, $this->setColSpanCell(2));
        $table->addCell(null, $this->setColSpanCell(count($axisX)))
            ->addText($this->anrTranslate($labelAxisX), $this->boldFont, $this->centerParagraph);
        $table->addRow(PhpWord\Shared\Converter::cmToTwip($size));
        $table->addCell(null, $this->rotate90TextCell)
            ->addText($this->anrTranslate('Impact'), $this->boldFont, $this->centerParagraph);
        $table->addCell(PhpWord\Shared\Converter::cmToTwip($size), $this->whiteBigBorderTable);

        foreach ($axisX as $x) {
            $table->addCell(PhpWord\Shared\Converter::cmToTwip($size), $this->whiteBigBorderTable)
                ->addText($x, $this->boldFont, $this->centerParagraph);
        }

        //row
        $nbLow = 0;
        $nbMedium = 0;
        $nbHigh = 0;
        foreach ($axisY as $y) {
            $table->addRow(PhpWord\Shared\Converter::cmToTwip($size));
            $table->addCell(null, $this->continueCell);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip($size), $this->whiteBigBorderTable)
                ->addText($y, $this->boldFont, $this->centerParagraph);

            foreach ($axisX as $x) {
                $value = $x * $y;
                if (isset($data[$y]) && isset($data[$y][$x])) {
                    $result = $data[$y][$x];
                } else {
                    $result = null;
                }

                $style = $this->whiteBigBorderTable;

                if ($value <= $thresholds[0]) {
                    $style['BgColor'] = 'D6F107';
                    if ($result) {
                        $nbLow += $result;
                    } else {
                        $style['BgColor'] = 'F0F7B2';
                    }
                } elseif ($value <= $thresholds[1]) {
                    $style['BgColor'] = 'FFBC1C';
                    if ($result) {
                        $nbMedium += $result;
                    } else {
                        $style['BgColor'] = 'FCDD94';
                    }
                } else {
                    $style['BgColor'] = 'FD661F';
                    if ($result) {
                        $nbHigh += $result;
                    } else {
                        $style['BgColor'] = 'FCB28F';
                    }
                }
                $table->addCell(PhpWord\Shared\Converter::cmToTwip($size), $style)
                    ->addText($result, $this->boldFont, $this->centerParagraph);
            }
        }

        //legend
        $maxSize = 7;
        $total = $nbLow + $nbMedium + $nbHigh;
        $lowSize = $total ? $maxSize * $nbLow / $total : 0;
        $mediumSize = $total ? $maxSize * $nbMedium / $total : 0;
        $highSize = $total ? $maxSize * $nbHigh / $total : 0;

        $section->addTextBreak(1);

        $tableLegend = $section->addTable();
        $tableLegend->addRow(PhpWord\Shared\Converter::cmToTwip(0.1));
        $tableLegend->addCell(PhpWord\Shared\Converter::cmToTwip(0.5), $this->continueCell);
        $tableLegend->addCell(PhpWord\Shared\Converter::cmToTwip(5), $this->whiteBigBorderTable)
            ->addText($nbLow . ' ' . $this->anrTranslate('Low risks'), $this->boldFont, $this->leftParagraph);
        if ($lowSize > 0) {
            $style = array_merge(
                $this->whiteBigBorderTable,
                ['BgColor' => 'D6F107', 'BorderTopSize' => 0, 'BorderBottomSize' => 30]
            );
            unset($style['BorderSize']);
            $tableLegend->addCell(PhpWord\Shared\Converter::cmToTwip($lowSize), $style);
        }

        if (($maxSize - $lowSize) !== 0) {
            $style['BgColor'] = 'F0F7B2';
            $tableLegend->addCell(PhpWord\Shared\Converter::cmToTwip($maxSize - $lowSize), $style);
        }

        $tableLegend = $section->addTable();
        $tableLegend->addRow(PhpWord\Shared\Converter::cmToTwip(0.1));
        $tableLegend->addCell(PhpWord\Shared\Converter::cmToTwip(0.5), $this->continueCell);
        $tableLegend->addCell(PhpWord\Shared\Converter::cmToTwip(5), $this->whiteBigBorderTable)
            ->addText($nbMedium . ' ' . $this->anrTranslate('Medium risks'), $this->boldFont, $this->leftParagraph);
        if ($mediumSize > 0) {
            $style = array_merge(
                $this->whiteBigBorderTable,
                ['BgColor' => 'FFBC1C', 'BorderTopSize' => 50, 'BorderBottomSize' => 30]
            );
            unset($style['BorderSize']);
            $tableLegend->addCell(PhpWord\Shared\Converter::cmToTwip($mediumSize), $style);
        }

        if (($maxSize - $mediumSize) !== 0) {
            $style['BgColor'] = 'FCDD94';
            $tableLegend->addCell(PhpWord\Shared\Converter::cmToTwip($maxSize - $mediumSize), $style);
        }

        $tableLegend = $section->addTable();
        $tableLegend->addRow(PhpWord\Shared\Converter::cmToTwip(0.1));
        $tableLegend->addCell(PhpWord\Shared\Converter::cmToTwip(0.5), $this->continueCell);
        $tableLegend->addCell(PhpWord\Shared\Converter::cmToTwip(5), $this->whiteBigBorderTable)
            ->addText($nbHigh . ' ' . $this->anrTranslate('High risks'), $this->boldFont, $this->leftParagraph);
        if ($highSize > 0) {
            $style = array_merge(
                $this->whiteBigBorderTable,
                ['BgColor' => 'FD661F', 'BorderTopSize' => 50, 'BorderBottomSize' => 30]
            );
            unset($style['BorderSize']);
            $tableLegend->addCell(PhpWord\Shared\Converter::cmToTwip($highSize), $style);
        }

        if (($maxSize - $highSize) !== 0) {
            $style['BgColor'] = 'FCB28F';
            $tableLegend->addCell(PhpWord\Shared\Converter::cmToTwip($maxSize - $highSize), $style);
        }

        return $section;
    }

    /**
     * Generates the risks graph that is included in the model
     * @return PhpWord\Element\Chart An array with the path and details of the generated canvas
     */
    private function generateRisksGraph($infoRisk = true): PhpWord\Element\Chart
    {
        $this->cartoRiskService->buildListScalesAndHeaders($this->anr);
        [$counters, $distrib] = $infoRisk
            ? $this->cartoRiskService->getCountersRisks($this->anr)
            : $this->cartoRiskService->getCountersOpRisks($this->anr);

        $categories = [
            $this->anrTranslate('Low risks'),
            $this->anrTranslate('Medium risks'),
            $this->anrTranslate('High risks'),
        ];

        $series = [
            $distrib[0] ?? 0,
            $distrib[1] ?? 0,
            $distrib[2] ?? 0,
        ];

        return (new PhpWord\PhpWord())->addSection()->addChart('column', $categories, $series, $this->barChart);
    }

    /**
     * Generate the audit table data
     * @return mixed|string The generated WordXml data
     */
    private function generateTableAudit()
    {
        $instanceRisks = $this->instanceRiskTable->findByAnrAndOrderByParams($this->anr, ['ir.cacheMaxRisk' => 'DESC']);

        $globalObject = [];
        $mem_risks = $globalObject;
        $maxLevelDeep = 1;

        foreach ($instanceRisks as $instanceRisk) {
            $instance = $instanceRisk->getInstance();
            $objectUuid = $instance->getObject()->getUuid();
            $threatUuid = $instanceRisk->getThreat()->getUuid();
            $vulnerabilityUuid = $instanceRisk->getVulnerability()->getUuid();
            if (!isset($globalObject[$objectUuid][$threatUuid][$vulnerabilityUuid])) {
                if ($instance->getObject()->isScopeGlobal()) {
                    $key = "o-" . $objectUuid;
                    if (!isset($mem_risks[$key])) {
                        $mem_risks[$key] = [
                            'ctx' => $instance->getName($this->currentLangAnrIndex)
                                . ' (' . $this->anrTranslate('Global') . ')',
                            'global' => true,
                            'risks' => [],
                        ];
                    }
                    $globalObject[$objectUuid][$threatUuid][$vulnerabilityUuid] = $objectUuid;
                } else {
                    $key = "i-" . $instance->getId();
                    if (!isset($mem_risks[$key])) {
                        $asc = $instance->getHierarchyArray();
                        $levelTree = count($asc);
                        if ($levelTree > $maxLevelDeep) {
                            $maxLevelDeep = $levelTree;
                        }

                        $mem_risks[$key] = [
                            'tree' => $asc,
                            'ctx' => $this->getInstancePathFromHierarchy($asc),
                            'global' => false,
                            'risks' => [],
                        ];

                        $parentInstance = $instance->getParent();
                        if ($parentInstance !== null && $instance->getRoot() !== null) {
                            for ($i = 0; $i < $levelTree - 2; $i++) {
                                if (!isset($mem_risks['i-' . $parentInstance->getId()])
                                    && $parentInstance->getId() !== $instance->getRoot()->getId()
                                ) {
                                    $asc = $parentInstance->getHierarchyArray();

                                    $mem_risks["i-" . $parentInstance->getId()] = [
                                        'tree' => $asc,
                                        'ctx' => $this->getInstancePathFromHierarchy($asc),
                                        'global' => false,
                                        'risks' => [],
                                    ];
                                } else {
                                    break;
                                }
                            }
                        }
                    }
                }

                $mem_risks[$key]['risks'][] = [
                    'impactC' => $instance->getConfidentiality(),
                    'impactI' => $instance->getIntegrity(),
                    'impactA' => $instance->getAvailability(),
                    'threat' => $instanceRisk->getThreat()->getLabel($this->currentLangAnrIndex),
                    'threatRate' => $instanceRisk->getThreatRate(),
                    'vulnerability' => $instanceRisk->getVulnerability()->getLabel($this->currentLangAnrIndex),
                    'comment' => $instanceRisk->getComment(),
                    'vulRate' => $instanceRisk->getVulnerabilityRate(),
                    'riskC' => $instanceRisk->getThreat()->getConfidentiality() === 0
                        ? null
                        : $instanceRisk->getRiskConfidentiality(),
                    'riskI' => $instanceRisk->getThreat()->getIntegrity() === 0
                        ? null
                        : $instanceRisk->getRiskIntegrity(),
                    'riskA' => $instanceRisk->getThreat()->getAvailability() === 0
                        ? null
                        : $instanceRisk->getRiskAvailability(),
                    'treatmentName' => $instanceRisk->getTreatmentName(),
                    'targetRisk' => $instanceRisk->getCacheTargetedRisk(),
                ];
            }
        }
        $ctx = array_column($mem_risks, 'ctx');
        $global = array_column($mem_risks, 'global');

        array_multisort($global, SORT_DESC, $ctx, SORT_ASC, $mem_risks);

        if (!empty($mem_risks)) {
            $maxLevelDeep = $maxLevelDeep <= 4 ? $maxLevelDeep : 4;
            $maxLevelTitle = $maxLevelDeep === 1 ? $maxLevelDeep : $maxLevelDeep - 1;
            $title = array_fill(0, $maxLevelDeep, null);

            $tableWord = new PhpWord\PhpWord();
            $section = $tableWord->addSection();
            for ($i = 0; $i < $maxLevelDeep + 1; $i++) {
                $tableWord->addTitleStyle($i + 3, $this->titleFont);
            }

            if (\in_array('true', $global, true)) {
                $section->addTitle($this->anrTranslate('Global assets'), 3);
            }

            foreach ($mem_risks as $data) {
                if (empty($data['tree'])) {
                    $section->addTitle(_WT($data['ctx']), 4);
                    $table = $section->addTable($this->borderTable);
                    $table->addRow(400, $this->tblHeader);
                    $table->addCell(PhpWord\Shared\Converter::cmToTwip(2.10), $this->setColSpanCell(3, '444444'))
                        ->addText($this->anrTranslate('Impact'), $this->whiteFont, $this->centerParagraph);
                    $table->addCell(PhpWord\Shared\Converter::cmToTwip(5.70), $this->setColSpanCell(2, '444444'))
                        ->addText($this->anrTranslate('Threat'), $this->whiteFont, $this->centerParagraph);
                    $table->addCell(PhpWord\Shared\Converter::cmToTwip(10.70), $this->setColSpanCell(3, '444444'))
                        ->addText($this->anrTranslate('Vulnerability'), $this->whiteFont, $this->centerParagraph);
                    $table->addCell(PhpWord\Shared\Converter::cmToTwip(2.10), $this->setColSpanCell(3, '444444'))
                        ->addText($this->anrTranslate('Current risk'), $this->whiteFont, $this->centerParagraph);
                    $table->addCell(PhpWord\Shared\Converter::cmToTwip(1.00), $this->restartAndBlackCell)
                        ->addText($this->anrTranslate('Treatment'), $this->whiteFont, $this->centerParagraph);
                    $table->addCell(PhpWord\Shared\Converter::cmToTwip(1.00), $this->restartAndBlackCell)
                        ->addText($this->anrTranslate('Residual risk'), $this->whiteFont, $this->centerParagraph);

                    $table->addRow(400, $this->tblHeader);
                    $table->addCell(PhpWord\Shared\Converter::cmToTwip(0.70), $this->blackCell)
                        ->addText('C', $this->whiteFont, $this->centerParagraph);
                    $table->addCell(PhpWord\Shared\Converter::cmToTwip(0.70), $this->blackCell)
                        ->addText('I', $this->whiteFont, $this->centerParagraph);
                    $table->addCell(PhpWord\Shared\Converter::cmToTwip(0.70), $this->blackCell)
                        ->addText($this->anrTranslate('A'), $this->whiteFont, $this->centerParagraph);
                    $table->addCell(PhpWord\Shared\Converter::cmToTwip(5.00), $this->blackCell)
                        ->addText($this->anrTranslate('Label'), $this->whiteFont, $this->centerParagraph);
                    $table->addCell(PhpWord\Shared\Converter::cmToTwip(0.70), $this->blackCell)
                        ->addText($this->anrTranslate('Prob.'), $this->whiteFont, $this->centerParagraph);
                    $table->addCell(PhpWord\Shared\Converter::cmToTwip(5.00), $this->blackCell)
                        ->addText($this->anrTranslate('Label'), $this->whiteFont, $this->centerParagraph);
                    $table->addCell(PhpWord\Shared\Converter::cmToTwip(5.00), $this->blackCell)
                        ->addText($this->anrTranslate('Existing controls'), $this->whiteFont, $this->centerParagraph);
                    $table->addCell(PhpWord\Shared\Converter::cmToTwip(0.70), $this->blackCell)
                        ->addText($this->anrTranslate('Qualif.'), $this->whiteFont, $this->centerParagraph);
                    $table->addCell(PhpWord\Shared\Converter::cmToTwip(0.70), $this->blackCell)
                        ->addText('C', $this->whiteFont, $this->centerParagraph);
                    $table->addCell(PhpWord\Shared\Converter::cmToTwip(0.70), $this->blackCell)
                        ->addText('I', $this->whiteFont, $this->centerParagraph);
                    $table->addCell(PhpWord\Shared\Converter::cmToTwip(0.70), $this->blackCell)
                        ->addText($this->anrTranslate('A'), $this->whiteFont, $this->centerParagraph);
                    $table->addCell(PhpWord\Shared\Converter::cmToTwip(1.00), $this->continueAndBlackCell);
                    $table->addCell(PhpWord\Shared\Converter::cmToTwip(1.00), $this->continueAndBlackCell);
                } else {
                    $treeNum = \count($data['tree']);
                    for ($i = 0; $i < $treeNum; $i++) {
                        if ($i <= $maxLevelTitle - 1 && $title[$i] !== $data['tree'][$i]['id']) {
                            $section->addTitle(_WT($data['tree'][$i]['name' . $this->currentLangAnrIndex]), $i + 3);
                            $title[$i] = $data['tree'][$i]['id'];
                            if ($maxLevelTitle === $treeNum && empty($data['risks'])) {
                                $data['risks'] = true;
                            }
                            if ($i === ($treeNum - 1) && !empty($data['risks'])) {
                                $section->addTextBreak();
                                $table = $section->addTable($this->borderTable);
                                $table->addRow(400, $this->tblHeader);
                                $table->addCell(
                                    PhpWord\Shared\Converter::cmToTwip(2.10),
                                    $this->setColSpanCell(3, '444444')
                                )->addText($this->anrTranslate('Impact'), $this->whiteFont, $this->centerParagraph);
                                $table->addCell(
                                    PhpWord\Shared\Converter::cmToTwip(5.70),
                                    $this->setColSpanCell(2, '444444')
                                )->addText($this->anrTranslate('Threat'), $this->whiteFont, $this->centerParagraph);
                                $table->addCell(
                                    PhpWord\Shared\Converter::cmToTwip(10.70),
                                    $this->setColSpanCell(3, '444444')
                                )->addText(
                                    $this->anrTranslate('Vulnerability'),
                                    $this->whiteFont,
                                    $this->centerParagraph
                                );
                                $table->addCell(
                                    PhpWord\Shared\Converter::cmToTwip(2.10),
                                    $this->setColSpanCell(3, '444444')
                                )->addText(
                                    $this->anrTranslate('Current risk'),
                                    $this->whiteFont,
                                    $this->centerParagraph
                                );
                                $table->addCell(PhpWord\Shared\Converter::cmToTwip(1.00), $this->restartAndBlackCell)
                                    ->addText(
                                        $this->anrTranslate('Treatment'),
                                        $this->whiteFont,
                                        $this->centerParagraph
                                    );
                                $table->addCell(PhpWord\Shared\Converter::cmToTwip(1.00), $this->restartAndBlackCell)
                                    ->addText(
                                        $this->anrTranslate('Residual risk'),
                                        $this->whiteFont,
                                        $this->centerParagraph
                                    );

                                $table->addRow(400, $this->tblHeader);
                                $table->addCell(PhpWord\Shared\Converter::cmToTwip(0.70), $this->blackCell)
                                    ->addText('C', $this->whiteFont, $this->centerParagraph);
                                $table->addCell(PhpWord\Shared\Converter::cmToTwip(0.70), $this->blackCell)
                                    ->addText('I', $this->whiteFont, $this->centerParagraph);
                                $table->addCell(PhpWord\Shared\Converter::cmToTwip(0.70), $this->blackCell)
                                    ->addText($this->anrTranslate('A'), $this->whiteFont, $this->centerParagraph);
                                $table->addCell(PhpWord\Shared\Converter::cmToTwip(5.00), $this->blackCell)
                                    ->addText($this->anrTranslate('Label'), $this->whiteFont, $this->centerParagraph);
                                $table->addCell(PhpWord\Shared\Converter::cmToTwip(0.70), $this->blackCell)
                                    ->addText($this->anrTranslate('Prob.'), $this->whiteFont, $this->centerParagraph);
                                $table->addCell(PhpWord\Shared\Converter::cmToTwip(5.00), $this->blackCell)
                                    ->addText($this->anrTranslate('Label'), $this->whiteFont, $this->centerParagraph);
                                $table->addCell(PhpWord\Shared\Converter::cmToTwip(5.00), $this->blackCell)
                                    ->addText(
                                        $this->anrTranslate('Existing controls'),
                                        $this->whiteFont,
                                        $this->centerParagraph
                                    );
                                $table->addCell(PhpWord\Shared\Converter::cmToTwip(0.70), $this->blackCell)
                                    ->addText($this->anrTranslate('Qualif.'), $this->whiteFont, $this->centerParagraph);
                                $table->addCell(PhpWord\Shared\Converter::cmToTwip(0.70), $this->blackCell)
                                    ->addText('C', $this->whiteFont, $this->centerParagraph);
                                $table->addCell(PhpWord\Shared\Converter::cmToTwip(0.70), $this->blackCell)
                                    ->addText('I', $this->whiteFont, $this->centerParagraph);
                                $table->addCell(PhpWord\Shared\Converter::cmToTwip(0.70), $this->blackCell)
                                    ->addText($this->anrTranslate('A'), $this->whiteFont, $this->centerParagraph);
                                $table->addCell(PhpWord\Shared\Converter::cmToTwip(1.00), $this->continueAndBlackCell);
                                $table->addCell(PhpWord\Shared\Converter::cmToTwip(1.00), $this->continueAndBlackCell);
                            }
                        }
                    }
                }

                if (!empty($data['risks']) && $data['risks'] !== true) {
                    if ($data['global'] === false) {
                        $table = $section->addTable($this->borderTable);
                        $table->addRow(400);
                        $table->addCell(PhpWord\Shared\Converter::cmToTwip(19.00), $this->setColSpanCell(13, 'DFDFDF'))
                            ->addText(_WT($data['ctx']), $this->boldFont, $this->leftParagraph);
                    }
                    foreach ($data['risks'] as $r) {
                        foreach ($r as $key => $value) {
                            if ($value === -1) {
                                $r[$key] = '-';
                            }
                        }

                        $table->addRow(400);
                        $table->addCell(PhpWord\Shared\Converter::cmToTwip(0.70), $this->vAlignCenterCell)
                            ->addText($r['impactC'], $this->normalFont, $this->centerParagraph);
                        $table->addCell(PhpWord\Shared\Converter::cmToTwip(0.70), $this->vAlignCenterCell)
                            ->addText($r['impactI'], $this->normalFont, $this->centerParagraph);
                        $table->addCell(PhpWord\Shared\Converter::cmToTwip(0.70), $this->vAlignCenterCell)
                            ->addText($r['impactA'], $this->normalFont, $this->centerParagraph);
                        $table->addCell(PhpWord\Shared\Converter::cmToTwip(5.00), $this->vAlignCenterCell)
                            ->addText(_WT($r['threat']), $this->normalFont, $this->leftParagraph);
                        $table->addCell(PhpWord\Shared\Converter::cmToTwip(0.70), $this->vAlignCenterCell)
                            ->addText($r['threatRate'], $this->normalFont, $this->centerParagraph);
                        $table->addCell(PhpWord\Shared\Converter::cmToTwip(5.00), $this->vAlignCenterCell)
                            ->addText(_WT($r['vulnerability']), $this->normalFont, $this->leftParagraph);
                        $table->addCell(PhpWord\Shared\Converter::cmToTwip(5.00), $this->vAlignCenterCell)
                            ->addText(_WT($r['comment']), $this->normalFont, $this->leftParagraph);
                        $table->addCell(PhpWord\Shared\Converter::cmToTwip(0.70), $this->vAlignCenterCell)
                            ->addText($r['vulRate'], $this->normalFont, $this->centerParagraph);
                        $table->addCell(PhpWord\Shared\Converter::cmToTwip(0.70), $this->setBgColorCell($r['riskC']))
                            ->addText($r['riskC'], $this->boldFont, $this->centerParagraph);
                        $table->addCell(PhpWord\Shared\Converter::cmToTwip(0.70), $this->setBgColorCell($r['riskI']))
                            ->addText($r['riskI'], $this->boldFont, $this->centerParagraph);
                        $table->addCell(PhpWord\Shared\Converter::cmToTwip(0.70), $this->setBgColorCell($r['riskA']))
                            ->addText($r['riskA'], $this->boldFont, $this->centerParagraph);
                        $table->addCell(PhpWord\Shared\Converter::cmToTwip(1.00), $this->vAlignCenterCell)
                            ->addText($r['treatmentName'], $this->normalFont, $this->leftParagraph);
                        $table->addCell(
                            PhpWord\Shared\Converter::cmToTwip(1.00),
                            $this->setBgColorCell($r['targetRisk'])
                        )->addText($r['targetRisk'], $this->boldFont, $this->centerParagraph);
                    }
                }
            }

            return $this->getWordXmlFromWordObject($tableWord);
        }
    }

    /**
     * Generates the audit table data for operational risks
     * @return mixed|string The generated WordXml data
     */
    private function generateTableAuditOp()
    {
        $operationalInstanceRisks = $this->instanceRiskOpTable->findByAnrAndOrderByParams(
            $this->anr,
            ['oprisk.cacheNetRisk' => 'DESC']
        );

        $lst = [];
        $maxLevelDeep = 1;

        foreach ($operationalInstanceRisks as $operationalInstanceRisk) {
            $instance = $operationalInstanceRisk->getInstance();
            if (!isset($lst[$instance->getId()])) {
                $ascendants = $instance->getHierarchyArray();
                $levelTree = \count($ascendants);
                if ($levelTree > $maxLevelDeep) {
                    $maxLevelDeep = $levelTree;
                }

                $parentInstance = $instance->getParent();
                $lst[$instance->getId()] = [
                    'tree' => $ascendants,
                    'path' => $this->getInstancePathFromHierarchy($ascendants),
                    'parent' => $parentInstance?->getId(),
                    'position' => $instance->getPosition(),
                    'risks' => [],
                ];

                foreach ($ascendants as $ascendant) {
                    if ($ascendant['parent'] !== null &&
                        $ascendant['root'] !== null &&
                        !isset($lst[$ascendant['id']])
                    ) {
                        $newAscendants = $ascendant['parent']->getHierarchyArray();
                        $lst[$ascendant['id']] = [
                            'tree' => $newAscendants,
                            'path' => $this->getInstancePathFromHierarchy($newAscendants),
                            'parent' => $ascendant['parent']->getId(),
                            'position' => $ascendant['position'],
                            'risks' => [],
                        ];
                    }
                }
            }

            $scalesData = [];
            foreach ($operationalInstanceRisk->getOperationalInstanceRiskScales() as $operationalInstanceRiskScale) {
                $operationalRiskScaleType = $operationalInstanceRiskScale->getOperationalRiskScaleType();
                $scalesData[$operationalRiskScaleType->getId()] = [
                    'netValue' => $operationalInstanceRiskScale->getNetValue() >= 0 ?
                        $operationalInstanceRiskScale->getNetValue() :
                        '-',
                    'brutValue' => $operationalInstanceRiskScale->getBrutValue() >= 0 ?
                        $operationalInstanceRiskScale->getBrutValue() :
                        '-',
                ];
            }

            $lst[$instance->getId()]['risks'][] = [
                'label' => $operationalInstanceRisk->getRiskCacheLabel($this->currentLangAnrIndex),
                'brutProb' => $operationalInstanceRisk->getBrutProb(),
                'brutRisk' => $operationalInstanceRisk->getCacheBrutRisk(),
                'netProb' => $operationalInstanceRisk->getNetProb(),
                'netRisk' => $operationalInstanceRisk->getCacheNetRisk(),
                'scales' => $scalesData,
                'comment' => $operationalInstanceRisk->getComment(),
                'targetedRisk' => $operationalInstanceRisk->getCacheTargetedRisk(),
                'treatmentName' => $operationalInstanceRisk->getTreatmentName(),
            ];
        }
        $tree = [];
        $rootInstances = $this->instanceTable->findRootsByAnr($this->anr);
        foreach ($rootInstances as $rootInstance) {
            $branchTree = $this->buildTree($lst, $rootInstance->getId());
            if ($branchTree) {
                $tree[$rootInstance->getId()] = $branchTree;
                $tree[$rootInstance->getId()]['position'] = $rootInstance->getPosition();
            }
        }

        $lst = [];
        usort($tree, static function ($a, $b) {
            return $a['position'] <=> $b['position'];
        });
        foreach ($tree as $branch) {
            unset($branch['position']);
            $flat_array = $this->singleLevelArray($branch);
            $lst = array_merge($lst, $flat_array);
        }

        if (!empty($lst)) {
            $opRisksAllScales = $this->operationalRiskScaleService->getOperationalRiskScales($this->anr);
            $opRisksImpactsScaleType = array_values(
                array_filter($opRisksAllScales, function ($scale) {
                    return $scale['type'] === 1;
                })
            );
            $opRisksImpactsScales = array_filter($opRisksImpactsScaleType[0]['scaleTypes'], function ($scale) {
                return $scale['isHidden'] === false;
            });
            $sizeCellImpact = count($opRisksImpactsScales) * 0.70;

            $tableWord = new PhpWord\PhpWord();
            $section = $tableWord->addSection();
            $maxLevelDeep = ($maxLevelDeep <= 4 ? $maxLevelDeep : 4);
            for ($i = 0; $i < $maxLevelDeep; $i++) {
                $tableWord->addTitleStyle($i + 3, $this->titleFont);
            }

            $maxLevelTitle = ($maxLevelDeep === 1 ? $maxLevelDeep : $maxLevelDeep - 1);

            $title = array_fill(0, $maxLevelDeep, null);

            foreach ($lst as $data) {
                $treeElementsNum = count($data['tree']);
                for ($i = 0; $i < $treeElementsNum; $i++) {
                    if ($i <= $maxLevelTitle - 1 && $title[$i] !== $data['tree'][$i]['id']) {
                        $section->addTitle(
                            _WT($data['tree'][$i]['name' . $this->currentLangAnrIndex]),
                            $i + 3
                        );
                        $title[$i] = $data['tree'][$i]['id'];
                        if (empty($data['risks']) && $maxLevelTitle === $treeElementsNum) {
                            $data['risks'] = true;
                        }
                        if (!empty($data['risks']) && $i === ($treeElementsNum - 1)) {
                            $section->addTextBreak();
                            $table = $section->addTable($this->borderTable);
                            $table->addRow(400, $this->tblHeader);
                            $table->addCell(PhpWord\Shared\Converter::cmToTwip(10.00), $this->restartAndBlackCell)
                                ->addText(
                                    $this->anrTranslate('Risk description'),
                                    $this->whiteFont,
                                    $this->centerParagraph
                                );
                            if ($this->anr->showRolfBrut()) {
                                $table->addCell(
                                    PhpWord\Shared\Converter::cmToTwip(5.50),
                                    $this->setColSpanCell(2 + count($opRisksImpactsScales), '444444')
                                )->addText(
                                    $this->anrTranslate('Inherent risk'),
                                    $this->whiteFont,
                                    $this->centerParagraph
                                );
                            }
                            $table->addCell(
                                PhpWord\Shared\Converter::cmToTwip(15.00),
                                $this->setColSpanCell(3 + count($opRisksImpactsScales), '444444')
                            )
                                ->addText($this->anrTranslate('Net risk'), $this->whiteFont, $this->centerParagraph);
                            $table->addCell(PhpWord\Shared\Converter::cmToTwip(2.00), $this->restartAndBlackCell)
                                ->addText($this->anrTranslate('Treatment'), $this->whiteFont, $this->centerParagraph);
                            $table->addCell(PhpWord\Shared\Converter::cmToTwip(2.00), $this->restartAndBlackCell)
                                ->addText(
                                    $this->anrTranslate('Residual risk'),
                                    $this->whiteFont,
                                    $this->centerParagraph
                                );

                            $table->addRow(400, $this->tblHeader);
                            $table->addCell(PhpWord\Shared\Converter::cmToTwip(10.00), $this->continueAndBlackCell);
                            if ($this->anr->showRolfBrut()) {
                                $table->addCell(PhpWord\Shared\Converter::cmToTwip(1.00), $this->restartAndBlackCell)
                                    ->addText($this->anrTranslate('Prob.'), $this->whiteFont,);
                                $table->addCell(
                                    PhpWord\Shared\Converter::cmToTwip($sizeCellImpact),
                                    $this->setColSpanCell(count($opRisksImpactsScales), '444444')
                                )->addText($this->anrTranslate('Impact'), $this->whiteFont, $this->centerParagraph);
                                $table->addCell(PhpWord\Shared\Converter::cmToTwip(1.00), $this->restartAndBlackCell)
                                    ->addText(
                                        $this->anrTranslate('Current risk'),
                                        $this->whiteFont,
                                        $this->centerParagraph
                                    );
                            }
                            $table->addCell(PhpWord\Shared\Converter::cmToTwip(1.00), $this->restartAndBlackCell)
                                ->addText($this->anrTranslate('Prob.'), $this->whiteFont, $this->centerParagraph);
                            $table->addCell(
                                PhpWord\Shared\Converter::cmToTwip($sizeCellImpact),
                                $this->setColSpanCell(count($opRisksImpactsScales), '444444')
                            )->addText($this->anrTranslate('Impact'), $this->whiteFont, $this->centerParagraph);
                            $table->addCell(PhpWord\Shared\Converter::cmToTwip(1.00), $this->restartAndBlackCell)
                                ->addText(
                                    $this->anrTranslate('Current risk'),
                                    $this->whiteFont,
                                    $this->centerParagraph
                                );
                            $table->addCell(PhpWord\Shared\Converter::cmToTwip(8.00), $this->restartAndBlackCell)
                                ->addText(
                                    $this->anrTranslate('Existing controls'),
                                    $this->whiteFont,
                                    $this->centerParagraph
                                );
                            $table->addCell(PhpWord\Shared\Converter::cmToTwip(2.00), $this->continueAndBlackCell);
                            $table->addCell(PhpWord\Shared\Converter::cmToTwip(2.00), $this->continueAndBlackCell);

                            $table->addRow(PhpWord\Shared\Converter::cmToTwip(1.00), $this->tblHeader);
                            $table->addCell(PhpWord\Shared\Converter::cmToTwip(10.00), $this->continueAndBlackCell);

                            if ($this->anr->showRolfBrut()) {
                                $table->addCell(PhpWord\Shared\Converter::cmToTwip(1.00), $this->continueAndBlackCell);
                                foreach ($opRisksImpactsScales as $opRiskImpactScale) {
                                    $label = mb_substr(_WT($opRiskImpactScale['label']), 0, 3) . '.';
                                    $table->addCell(
                                        PhpWord\Shared\Converter::cmToTwip(0.70),
                                        array_merge($this->rotate90TextCell, ['bgcolor' => '444444'])
                                    )
                                        ->addText($label, $this->whiteFont,);
                                }
                                $table->addCell(PhpWord\Shared\Converter::cmToTwip(1.00), $this->continueAndBlackCell);
                            }
                            $table->addCell(PhpWord\Shared\Converter::cmToTwip(1.00), $this->continueAndBlackCell);
                            foreach ($opRisksImpactsScales as $opRiskImpactScale) {
                                $label = mb_substr(_WT($opRiskImpactScale['label']), 0, 3) . '.';
                                $table->addCell(
                                    PhpWord\Shared\Converter::cmToTwip(0.70),
                                    array_merge($this->rotate90TextCell, ['bgcolor' => '444444'])
                                )
                                    ->addText($label, $this->whiteFont, $this->verticalCenterParagraph);
                            }
                            $table->addCell(PhpWord\Shared\Converter::cmToTwip(1.00), $this->continueAndBlackCell);
                            $table->addCell(PhpWord\Shared\Converter::cmToTwip(8.00), $this->continueAndBlackCell);
                            $table->addCell(PhpWord\Shared\Converter::cmToTwip(2.00), $this->continueAndBlackCell);
                            $table->addCell(PhpWord\Shared\Converter::cmToTwip(2.00), $this->continueAndBlackCell);
                        }
                    }
                }

                if (!empty($data['risks']) && $data['risks'] !== true) {
                    $styleCell = $this->setColSpanCell(6 + count($opRisksImpactsScales), 'DFDFDF');
                    if ($this->anr->showRolfBrut()) {
                        $styleCell = $this->setColSpanCell(8 + count($opRisksImpactsScales) * 2, 'DFDFDF');
                    }
                    $table = $section->addTable($this->borderTable);
                    $table->addRow(400);
                    $table->addCell(PhpWord\Shared\Converter::cmToTwip(19.00), $styleCell)
                        ->addText(_WT($data['path']), $this->boldFont, $this->leftParagraph);
                    foreach ($data['risks'] as $r) {
                        foreach ($r as $key => $value) {
                            if ($value === -1) {
                                $r[$key] = '-';
                            }
                        }
                        $table->addRow(400);
                        $table->addCell(PhpWord\Shared\Converter::cmToTwip(10.00), $this->vAlignCenterCell)
                            ->addText(_WT($r['label']), $this->normalFont, $this->leftParagraph);
                        if ($this->anr->showRolfBrut()) {
                            $table->addCell(PhpWord\Shared\Converter::cmToTwip(1.00), $this->vAlignCenterCell)
                                ->addText($r['brutProb'], $this->normalFont, $this->centerParagraph);
                            foreach ($opRisksImpactsScales as $opRiskImpactScale) {
                                $table->addCell(PhpWord\Shared\Converter::cmToTwip(0.70), $this->vAlignCenterCell)
                                    ->addText(
                                        $r['scales'][$opRiskImpactScale['id']]['brutValue'],
                                        $this->normalFont,
                                        $this->centerParagraph
                                    );
                            }
                            $table->addCell(
                                PhpWord\Shared\Converter::cmToTwip(1.00),
                                $this->setBgColorCell($r['brutRisk'], false)
                            )->addText($r['brutRisk'], $this->boldFont, $this->centerParagraph);
                        }
                        $table->addCell(PhpWord\Shared\Converter::cmToTwip(1.00), $this->vAlignCenterCell)
                            ->addText($r['netProb'], $this->normalFont, $this->centerParagraph);
                        foreach ($opRisksImpactsScales as $opRiskImpactScale) {
                            $table->addCell(PhpWord\Shared\Converter::cmToTwip(0.70), $this->vAlignCenterCell)
                                ->addText(
                                    $r['scales'][$opRiskImpactScale['id']]['netValue'],
                                    $this->normalFont,
                                    $this->centerParagraph
                                );
                        }
                        $table->addCell(
                            PhpWord\Shared\Converter::cmToTwip(1.00),
                            $this->setBgColorCell($r['netRisk'], false)
                        )->addText($r['netRisk'], $this->boldFont, $this->centerParagraph);
                        $table->addCell(PhpWord\Shared\Converter::cmToTwip(8.00), $this->vAlignCenterCell)
                            ->addText(_WT($r['comment']), $this->normalFont, $this->leftParagraph);
                        $table->addCell(PhpWord\Shared\Converter::cmToTwip(2.00), $this->vAlignCenterCell)
                            ->addText($r['treatmentName'], $this->normalFont, $this->leftParagraph);
                        $targetedRisk = $r['targetedRisk'] === '-' ? $r['netRisk'] : $r['targetedRisk'];
                        $table->addCell(
                            PhpWord\Shared\Converter::cmToTwip(2.00),
                            $this->setBgColorCell($targetedRisk, false)
                        )->addText($targetedRisk, $this->boldFont, $this->centerParagraph);
                    }
                }
            }

            return $this->getWordXmlFromWordObject($tableWord);
        }
    }

    /**
     * Generates Word-compliant HTML for the risks' distribution paragraph.
     *
     * @return string HTML data that can be converted into WordXml data
     */
    private function getRisksDistribution($infoRisk = true)
    {
        $this->cartoRiskService->buildListScalesAndHeaders($this->anr);
        [$counters, $distrib] = $infoRisk
            ? $this->cartoRiskService->getCountersRisks($this->anr)
            : $this->cartoRiskService->getCountersOpRisks($this->anr);

        $sum = 0;
        foreach ([0, 1, 2] as $color) {
            if (!isset($distrib[$color])) {
                $distrib[$color] = 0;
            }
            $sum += $distrib[$color];
        }

        $intro = sprintf($this->anrTranslate(
            "The list of risks addressed is provided as an attachment. It lists %d risk(s) of which:"
        ), $sum);

        return $intro .
            "<!--block-->&nbsp;&nbsp;- " .
            $distrib[2] .
            ' ' .
            $this->anrTranslate('critical risk(s) to be treated as priority') .
            "<!--block-->" .
            "<!--block-->&nbsp;&nbsp;- " .
            $distrib[1] .
            ' ' .
            $this->anrTranslate('medium risk(s) to be partially treated') .
            "<!--block-->" .
            "<!--block-->&nbsp;&nbsp;- " .
            $distrib[0] .
            ' ' .
            $this->anrTranslate('low risk(s) negligible') . "<!--block-->";
    }

    /**
     * Generates the Risks by kind of treatment.
     *
     * @return mixed|string The WordXml data generated
     */
    private function generateRisksByKindOfMeasure()
    {
        $result = null;
        $opRisksAllScales = $this->operationalRiskScaleService->getOperationalRiskScales($this->anr);
        $opRisksImpactsScaleType = array_values(
            array_filter($opRisksAllScales, static function ($scale) {
                return $scale['type'] === OperationalRiskScaleSuperClass::TYPE_IMPACT;
            })
        );
        $opRisksImpactsScales = array_filter($opRisksImpactsScaleType[0]['scaleTypes'], static function ($scale) {
            return $scale['isHidden'] === false;
        });
        $sizeCellImpact = count($opRisksImpactsScales) * 0.70;

        for ($i = InstanceRiskSuperClass::KIND_REDUCTION; $i <= InstanceRiskSuperClass::KIND_SHARED; $i++) {
            $risksByTreatment = $this->anrInstanceRiskService->getInstanceRisks(
                $this->anr,
                null,
                ['limit' => -1, 'order' => 'maxRisk', 'order_direction' => 'desc', 'kindOfMeasure' => $i]
            );
            $risksOpByTreatment = $this->anrInstanceRiskOpService->getOperationalRisks(
                $this->anr,
                null,
                ['limit' => -1, 'order' => 'cacheNetRisk', 'order_direction' => 'desc', 'kindOfMeasure' => $i]
            );

            //create section
            $tableWord = new PhpWord\PhpWord();
            $section = $tableWord->addSection();
            $title = false;

            if (!empty($risksByTreatment)) {
                $title = true;
                $tableTitle = $section->addTable($this->noBorderTable);
                $tableTitle->addRow(400);
                $tableTitle->addCell(PhpWord\Shared\Converter::cmToTwip(10.00))->addText(
                    InstanceRiskSuperClass::getTreatmentNameByType($i),
                    $this->titleFont,
                    $this->leftParagraph
                );
                $tableRiskInfo = $section->addTable($this->borderTable);

                $tableRiskInfo->addRow(400);
                $tableRiskInfo->addCell(PhpWord\Shared\Converter::cmToTwip(3.00), $this->restartAndGrayCell)
                    ->addText($this->anrTranslate('Asset'), $this->boldFont, $this->centerParagraph);
                $tableRiskInfo->addCell(PhpWord\Shared\Converter::cmToTwip(2.10), $this->setColSpanCell(3, 'DFDFDF'))
                    ->addText($this->anrTranslate('Impact'), $this->boldFont, $this->centerParagraph);
                $tableRiskInfo->addCell(PhpWord\Shared\Converter::cmToTwip(5.50), $this->setColSpanCell(2, 'DFDFDF'))
                    ->addText($this->anrTranslate('Threat'), $this->boldFont, $this->centerParagraph);
                $tableRiskInfo->addCell(PhpWord\Shared\Converter::cmToTwip(10.00), $this->setColSpanCell(3, 'DFDFDF'))
                    ->addText($this->anrTranslate('Vulnerability'), $this->boldFont, $this->centerParagraph);
                $tableRiskInfo->addCell(PhpWord\Shared\Converter::cmToTwip(3.00), $this->setColSpanCell(3, 'DFDFDF'))
                    ->addText($this->anrTranslate('Current risk'), $this->boldFont, $this->centerParagraph);
                $tableRiskInfo->addCell(PhpWord\Shared\Converter::cmToTwip(2.00), $this->restartAndGrayCell)
                    ->addText($this->anrTranslate('Residual risk'), $this->boldFont, $this->centerParagraph);
                $tableRiskInfo->addRow(400);
                $tableRiskInfo->addCell(PhpWord\Shared\Converter::cmToTwip(3.00), $this->continueAndGrayCell);
                $tableRiskInfo->addCell(PhpWord\Shared\Converter::cmToTwip(0.70), $this->grayCell)
                    ->addText('C', $this->boldFont, $this->centerParagraph);
                $tableRiskInfo->addCell(PhpWord\Shared\Converter::cmToTwip(0.70), $this->grayCell)
                    ->addText('I', $this->boldFont, $this->centerParagraph);
                $tableRiskInfo->addCell(PhpWord\Shared\Converter::cmToTwip(0.70), $this->grayCell)
                    ->addText($this->anrTranslate('A'), $this->boldFont, $this->centerParagraph);
                $tableRiskInfo->addCell(PhpWord\Shared\Converter::cmToTwip(3.50), $this->grayCell)
                    ->addText($this->anrTranslate('Label'), $this->boldFont, $this->centerParagraph);
                $tableRiskInfo->addCell(PhpWord\Shared\Converter::cmToTwip(2.00), $this->grayCell)
                    ->addText($this->anrTranslate('Prob.'), $this->boldFont, $this->centerParagraph);
                $tableRiskInfo->addCell(PhpWord\Shared\Converter::cmToTwip(4.00), $this->grayCell)
                    ->addText($this->anrTranslate('Label'), $this->boldFont, $this->centerParagraph);
                $tableRiskInfo->addCell(PhpWord\Shared\Converter::cmToTwip(4.00), $this->grayCell)
                    ->addText($this->anrTranslate('Existing controls'), $this->boldFont, $this->centerParagraph);
                $tableRiskInfo->addCell(PhpWord\Shared\Converter::cmToTwip(2.00), $this->grayCell)
                    ->addText($this->anrTranslate('Qualif.'), $this->boldFont, $this->centerParagraph);
                $tableRiskInfo->addCell(PhpWord\Shared\Converter::cmToTwip(1.00), $this->grayCell)
                    ->addText('C', $this->boldFont, $this->centerParagraph);
                $tableRiskInfo->addCell(PhpWord\Shared\Converter::cmToTwip(1.00), $this->grayCell)
                    ->addText('I', $this->boldFont, $this->centerParagraph);
                $tableRiskInfo->addCell(PhpWord\Shared\Converter::cmToTwip(1.00), $this->grayCell)
                    ->addText($this->anrTranslate('A'), $this->boldFont, $this->centerParagraph);
                $tableRiskInfo->addCell(PhpWord\Shared\Converter::cmToTwip(2.00), $this->continueAndGrayCell);

                $impacts = ['c', 'i', 'd'];
                foreach ($risksByTreatment as $r) {
                    foreach ($impacts as $impact) {
                        if ($r[$impact . '_risk_enabled'] === 0) {
                            $r[$impact . '_risk'] = null;
                        }
                    }
                    foreach ($r as $key => $value) {
                        if ($value === -1) {
                            $r[$key] = '-';
                        }
                    }
                    /** @var Entity\Instance $instance */
                    $instance = $this->instanceTable->findByIdAndAnr($r['instance'], $this->anr);
                    if (!$instance->getObject()->isScopeGlobal()) {
                        $path = $instance->getHierarchyString();
                    } else {
                        $path = $instance->getName($this->currentLangAnrIndex)
                            . ' (' . $this->anrTranslate('Global') . ')';
                    }

                    $tableRiskInfo->addRow(400);
                    $tableRiskInfo->addCell(PhpWord\Shared\Converter::cmToTwip(3.00), $this->vAlignCenterCell)
                        ->addText(_WT($path), $this->normalFont, $this->leftParagraph);
                    $tableRiskInfo->addCell(PhpWord\Shared\Converter::cmToTwip(0.70), $this->vAlignCenterCell)
                        ->addText($r['c_impact'], $this->normalFont, $this->centerParagraph);
                    $tableRiskInfo->addCell(PhpWord\Shared\Converter::cmToTwip(0.70), $this->vAlignCenterCell)
                        ->addText($r['i_impact'], $this->normalFont, $this->centerParagraph);
                    $tableRiskInfo->addCell(PhpWord\Shared\Converter::cmToTwip(0.70), $this->vAlignCenterCell)
                        ->addText($r['d_impact'], $this->normalFont, $this->centerParagraph);
                    $tableRiskInfo->addCell(PhpWord\Shared\Converter::cmToTwip(3.50), $this->vAlignCenterCell)
                        ->addText(
                            _WT($r['threatLabel' . $this->currentLangAnrIndex]),
                            $this->normalFont,
                            $this->leftParagraph
                        );
                    $tableRiskInfo->addCell(PhpWord\Shared\Converter::cmToTwip(2.00), $this->vAlignCenterCell)
                        ->addText($r['threatRate'], $this->normalFont, $this->centerParagraph);
                    $tableRiskInfo->addCell(PhpWord\Shared\Converter::cmToTwip(4.00), $this->vAlignCenterCell)
                        ->addText(
                            _WT($r['vulnLabel' . $this->currentLangAnrIndex]),
                            $this->normalFont,
                            $this->leftParagraph
                        );
                    $tableRiskInfo->addCell(PhpWord\Shared\Converter::cmToTwip(4.00), $this->vAlignCenterCell)
                        ->addText(_WT($r['comment']), $this->normalFont, $this->leftParagraph);
                    $tableRiskInfo->addCell(PhpWord\Shared\Converter::cmToTwip(2.00), $this->vAlignCenterCell)
                        ->addText($r['vulnerabilityRate'], $this->normalFont, $this->centerParagraph);
                    $tableRiskInfo
                        ->addCell(PhpWord\Shared\Converter::cmToTwip(1.00), $this->setBgColorCell($r['c_risk']))
                        ->addText($r['c_risk'], $this->boldFont, $this->centerParagraph);
                    $tableRiskInfo
                        ->addCell(PhpWord\Shared\Converter::cmToTwip(1.00), $this->setBgColorCell($r['i_risk']))
                        ->addText($r['i_risk'], $this->boldFont, $this->centerParagraph);
                    $tableRiskInfo
                        ->addCell(PhpWord\Shared\Converter::cmToTwip(1.00), $this->setBgColorCell($r['d_risk']))
                        ->addText($r['d_risk'], $this->boldFont, $this->centerParagraph);
                    $tableRiskInfo
                        ->addCell(PhpWord\Shared\Converter::cmToTwip(2.00), $this->setBgColorCell($r['target_risk']))
                        ->addText($r['target_risk'], $this->boldFont, $this->centerParagraph);
                }
                $section->addTextBreak();
            }
            if (!empty($risksOpByTreatment)) {
                if (!$title) {
                    $tableTitle = $section->addTable($this->noBorderTable);
                    $tableTitle->addRow(400);
                    $tableTitle->addCell(PhpWord\Shared\Converter::cmToTwip(10.00), $this->setColSpanCell(13))->addText(
                        InstanceRiskOpSuperClass::getTreatmentNameByType($i),
                        $this->titleFont,
                        $this->leftParagraph
                    );
                }
                $tableRiskOp = $section->addTable($this->borderTable);

                $tableRiskOp->addRow(400);
                $tableRiskOp->addCell(PhpWord\Shared\Converter::cmToTwip(3.00), $this->restartAndGrayCell)
                    ->addText($this->anrTranslate('Asset'), $this->boldFont, $this->centerParagraph);
                $tableRiskOp->addCell(PhpWord\Shared\Converter::cmToTwip(10.00), $this->restartAndGrayCell)
                    ->addText($this->anrTranslate('Risk description'), $this->boldFont, $this->centerParagraph);
                if ($this->anr->showRolfBrut()) {
                    $tableRiskOp->addCell(
                        PhpWord\Shared\Converter::cmToTwip(5.50),
                        $this->setColSpanCell(2 + count($opRisksImpactsScales), 'DFDFDF')
                    )->addText($this->anrTranslate('Inherent risk'), $this->boldFont, $this->centerParagraph);
                }
                $tableRiskOp->addCell(
                    PhpWord\Shared\Converter::cmToTwip(15.00),
                    $this->setColSpanCell(3 + count($opRisksImpactsScales), 'DFDFDF')
                )->addText($this->anrTranslate('Net risk'), $this->boldFont, $this->centerParagraph);
                $tableRiskOp->addCell(PhpWord\Shared\Converter::cmToTwip(2.00), $this->restartAndGrayCell)
                    ->addText($this->anrTranslate('Residual risk'), $this->boldFont, $this->centerParagraph);

                $tableRiskOp->addRow(400, $this->tblHeader);
                $tableRiskOp->addCell(PhpWord\Shared\Converter::cmToTwip(3.00), $this->continueAndGrayCell);
                $tableRiskOp->addCell(PhpWord\Shared\Converter::cmToTwip(10.00), $this->continueAndGrayCell);
                if ($this->anr->showRolfBrut()) {
                    $tableRiskOp->addCell(PhpWord\Shared\Converter::cmToTwip(1.00), $this->restartAndGrayCell)
                        ->addText($this->anrTranslate('Prob.'), $this->boldFont, $this->centerParagraph);
                    $tableRiskOp->addCell(
                        PhpWord\Shared\Converter::cmToTwip($sizeCellImpact),
                        $this->setColSpanCell(count($opRisksImpactsScales), 'DFDFDF')
                    )->addText($this->anrTranslate('Impact'), $this->boldFont, $this->centerParagraph);
                    $tableRiskOp->addCell(PhpWord\Shared\Converter::cmToTwip(1.00), $this->restartAndGrayCell)
                        ->addText($this->anrTranslate('Current risk'), $this->boldFont, $this->centerParagraph);
                }
                $tableRiskOp->addCell(PhpWord\Shared\Converter::cmToTwip(1.00), $this->restartAndGrayCell)
                    ->addText($this->anrTranslate('Prob.'), $this->boldFont, $this->centerParagraph);
                $tableRiskOp->addCell(
                    PhpWord\Shared\Converter::cmToTwip($sizeCellImpact),
                    $this->setColSpanCell(count($opRisksImpactsScales), 'DFDFDF')
                )->addText($this->anrTranslate('Impact'), $this->boldFont, $this->centerParagraph);
                $tableRiskOp->addCell(PhpWord\Shared\Converter::cmToTwip(1.00), $this->restartAndGrayCell)
                    ->addText($this->anrTranslate('Current risk'), $this->boldFont, $this->centerParagraph);
                $tableRiskOp->addCell(PhpWord\Shared\Converter::cmToTwip(8.00), $this->restartAndGrayCell)
                    ->addText($this->anrTranslate('Existing controls'), $this->boldFont, $this->centerParagraph);
                $tableRiskOp->addCell(PhpWord\Shared\Converter::cmToTwip(2.00), $this->continueAndGrayCell);

                $tableRiskOp->addRow(PhpWord\Shared\Converter::cmToTwip(1.00));
                $tableRiskOp->addCell(PhpWord\Shared\Converter::cmToTwip(3.00), $this->continueAndGrayCell);
                $tableRiskOp->addCell(PhpWord\Shared\Converter::cmToTwip(10.00), $this->continueAndGrayCell);
                if ($this->anr->showRolfBrut()) {
                    $tableRiskOp->addCell(PhpWord\Shared\Converter::cmToTwip(1.00), $this->continueAndGrayCell);
                    foreach ($opRisksImpactsScales as $opRiskImpactScale) {
                        $label = mb_substr(_WT($opRiskImpactScale['label']), 0, 3) . '.';
                        $tableRiskOp->addCell(
                            PhpWord\Shared\Converter::cmToTwip(0.70),
                            array_merge($this->rotate90TextCell, ['bgcolor' => 'DFDFDF'])
                        )->addText($label, $this->boldFont, $this->verticalCenterParagraph);
                    }
                    $tableRiskOp->addCell(PhpWord\Shared\Converter::cmToTwip(1.00), $this->continueAndGrayCell);
                }
                $tableRiskOp->addCell(PhpWord\Shared\Converter::cmToTwip(1.00), $this->continueAndGrayCell);
                foreach ($opRisksImpactsScales as $opRiskImpactScale) {
                    $label = mb_substr(_WT($opRiskImpactScale['label']), 0, 3) . '.';
                    $tableRiskOp->addCell(
                        PhpWord\Shared\Converter::cmToTwip(0.70),
                        array_merge($this->rotate90TextCell, ['bgcolor' => 'DFDFDF'])
                    )->addText($label, $this->boldFont, $this->verticalCenterParagraph);
                }
                $tableRiskOp->addCell(PhpWord\Shared\Converter::cmToTwip(1.00), $this->continueAndGrayCell);
                $tableRiskOp->addCell(PhpWord\Shared\Converter::cmToTwip(8.00), $this->continueAndGrayCell);
                $tableRiskOp->addCell(PhpWord\Shared\Converter::cmToTwip(2.00), $this->continueAndGrayCell);

                foreach ($risksOpByTreatment as $r) {
                    /** @var Entity\InstanceRiskOp $instanceRiskOp */
                    $instanceRiskOp = $this->instanceRiskOpTable->findByIdAndAnr((int)$r['id'], $this->anr);
                    foreach ($instanceRiskOp->getOperationalInstanceRiskScales() as $operationalInstanceRiskScale) {
                        $operationalRiskScaleType = $operationalInstanceRiskScale->getOperationalRiskScaleType();
                        $scalesData[$operationalRiskScaleType->getId()] = [
                            'netValue' => $operationalInstanceRiskScale
                                ->getNetValue() >= 0 ? $operationalInstanceRiskScale->getNetValue() : '-',
                            'brutValue' => $operationalInstanceRiskScale
                                ->getBrutValue() >= 0 ? $operationalInstanceRiskScale->getBrutValue() : '-',
                        ];
                    }

                    $r['scales'] = $scalesData;

                    foreach ($r as $key => $value) {
                        if ($value === -1) {
                            $r[$key] = '-';
                        }
                    }

                    /** @var Entity\Instance $instance */
                    $instance = $this->instanceTable->findByIdAndAnr($r['instanceInfos']['id'], $this->anr);
                    $path = $instance->getHierarchyString();

                    $tableRiskOp->addRow(400);
                    $tableRiskOp->addCell(PhpWord\Shared\Converter::cmToTwip(3.00), $this->vAlignCenterCell)
                        ->addText(_WT($path), $this->normalFont, $this->leftParagraph);
                    $tableRiskOp->addCell(PhpWord\Shared\Converter::cmToTwip(10.00), $this->vAlignCenterCell)
                        ->addText(
                            _WT($r['label' . $this->currentLangAnrIndex]),
                            $this->normalFont,
                            $this->leftParagraph
                        );
                    if ($this->anr->showRolfBrut()) {
                        $tableRiskOp->addCell(PhpWord\Shared\Converter::cmToTwip(1.00), $this->vAlignCenterCell)
                            ->addText($r['brutProb'], $this->normalFont, $this->centerParagraph);
                        foreach ($opRisksImpactsScales as $opRiskImpactScale) {
                            $tableRiskOp->addCell(PhpWord\Shared\Converter::cmToTwip(0.70), $this->vAlignCenterCell)
                                ->addText(
                                    $r['scales'][$opRiskImpactScale['id']]['brutValue'],
                                    $this->normalFont,
                                    $this->centerParagraph
                                );
                        }
                        $tableRiskOp->addCell(
                            PhpWord\Shared\Converter::cmToTwip(1.00),
                            $this->setBgColorCell($r['cacheBrutRisk'], false)
                        )->addText($r['cacheBrutRisk'], $this->boldFont, $this->centerParagraph);
                    }
                    $tableRiskOp->addCell(PhpWord\Shared\Converter::cmToTwip(1.00), $this->vAlignCenterCell)
                        ->addText($r['netProb'], $this->normalFont, $this->centerParagraph);
                    foreach ($opRisksImpactsScales as $opRiskImpactScale) {
                        $tableRiskOp->addCell(PhpWord\Shared\Converter::cmToTwip(0.70), $this->vAlignCenterCell)
                            ->addText(
                                $r['scales'][$opRiskImpactScale['id']]['netValue'],
                                $this->normalFont,
                                $this->centerParagraph
                            );
                    }
                    $tableRiskOp->addCell(
                        PhpWord\Shared\Converter::cmToTwip(1.00),
                        $this->setBgColorCell($r['cacheNetRisk'], false)
                    )->addText($r['cacheNetRisk'], $this->boldFont, $this->centerParagraph);
                    $tableRiskOp->addCell(PhpWord\Shared\Converter::cmToTwip(8.00), $this->vAlignCenterCell)
                        ->addText(_WT($r['comment']), $this->normalFont, $this->leftParagraph);
                    $cacheTargetedRisk = $r['cacheTargetedRisk'] === '-' ? $r['cacheNetRisk'] : $r['cacheTargetedRisk'];
                    $tableRiskOp->addCell(
                        PhpWord\Shared\Converter::cmToTwip(2.00),
                        $this->setBgColorCell($cacheTargetedRisk, false)
                    )->addText($cacheTargetedRisk, $this->boldFont, $this->centerParagraph);
                }
                $section->addTextBreak();
            }
            $result .= $this->getWordXmlFromWordObject($tableWord);
        }

        return $result;
    }

    /**
     * Generates the Risks Plan data
     * @return mixed|string The WordXml data generated
     */
    private function generateRisksPlan()
    {
        $recommendationRisks = $this->recommendationRiskTable->findByAnrOrderByAndCanExcludeNotTreated(
            $this->anr,
            ['r.position' => 'ASC']
        );

        $tableWord = new PhpWord\PhpWord();
        $section = $tableWord->addSection();
        $table = $section->addTable($this->borderTable);

        if (!empty($recommendationRisks)) {
            $table->addRow(400, $this->tblHeader);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(3.50), $this->restartAndGrayCell)
                ->addText($this->anrTranslate('Asset'), $this->boldFont, $this->centerParagraph);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(6.00), $this->restartAndGrayCell)
                ->addText($this->anrTranslate('Threat'), $this->boldFont, $this->centerParagraph);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(6.00), $this->restartAndGrayCell)
                ->addText($this->anrTranslate('Vulnerability'), $this->boldFont, $this->centerParagraph);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(6.00), $this->restartAndGrayCell)
                ->addText($this->anrTranslate('Existing controls'), $this->boldFont, $this->centerParagraph);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(2.10), $this->setColSpanCell(3, 'DFDFDF'))
                ->addText($this->anrTranslate('Current risk'), $this->boldFont, $this->centerParagraph);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(2.10), $this->restartAndGrayCell)
                ->addText($this->anrTranslate('Treatment'), $this->boldFont, $this->centerParagraph);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(2.10), $this->restartAndGrayCell)
                ->addText($this->anrTranslate('Residual risk'), $this->boldFont, $this->centerParagraph);

            $table->addRow();
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(3.00), $this->continueAndGrayCell);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(6.00), $this->continueAndGrayCell);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(6.00), $this->continueAndGrayCell);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(6.00), $this->continueAndGrayCell);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(0.70), $this->grayCell)
                ->addText('C', $this->boldFont, $this->centerParagraph);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(0.70), $this->grayCell)
                ->addText('I', $this->boldFont, $this->centerParagraph);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(0.70), $this->grayCell)
                ->addText($this->anrTranslate('A'), $this->boldFont, $this->centerParagraph);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(2.10), $this->continueAndGrayCell);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(2.10), $this->continueAndGrayCell);
        }

        $global = [];
        $toUnset = [];
        foreach ($recommendationRisks as $recommendationRisk) {
            $instanceRisk = $recommendationRisk->getInstanceRisk();
            if ($instanceRisk !== null && $recommendationRisk->hasGlobalObjectRelation()) {
                $key = $recommendationRisk->getRecommendation()->getUuid()
                    . ' - ' . $recommendationRisk->getThreat()?->getUuid()
                    . ' - ' . $recommendationRisk->getVulnerability()?->getUuid()
                    . ' - ' . $recommendationRisk->getGlobalObject()?->getUuid();
                if (array_key_exists($key, $global)) {
                    if (array_key_exists($key, $toUnset) && $instanceRisk->getCacheMaxRisk() > $toUnset[$key]) {
                        $toUnset[$key] = $instanceRisk->getCacheMaxRisk();
                    } else {
                        $toUnset[$key] = max($instanceRisk->getCacheMaxRisk(), $global[$key]);
                    }
                }
                $global[$key] = $instanceRisk->getCacheMaxRisk();
            }
        }


        $previousRecoId = null;
        $alreadySet = [];
        foreach ($recommendationRisks as $recommendationRisk) {
            $instanceRisk = $recommendationRisk->getInstanceRisk();
            if ($instanceRisk !== null && $recommendationRisk->getThreat() !== null) {
                $riskConfidentiality = null;
                $riskAvailability = null;
                $riskIntegrity = null;
                if ($recommendationRisk->getThreat()->getConfidentiality()) {
                    $riskConfidentiality = $instanceRisk->getRiskConfidentiality() === -1
                        ? '-'
                        : $instanceRisk->getRiskConfidentiality();
                }
                if ($recommendationRisk->getThreat()->getIntegrity()) {
                    $riskIntegrity = $instanceRisk->getRiskIntegrity() === -1 ? '-' : $instanceRisk->getRiskIntegrity();
                }
                if ($recommendationRisk->getThreat()->getAvailability()) {
                    $riskAvailability = $instanceRisk->getRiskAvailability() === -1
                        ? '-'
                        : $instanceRisk->getRiskAvailability();
                }

                $importance = str_repeat('●', $recommendationRisk->getRecommendation()->getImportance());

                if ($recommendationRisk->getRecommendation()->getUuid() !== $previousRecoId) {
                    $table->addRow(400);
                    $cellReco = $table->addCell(
                        PhpWord\Shared\Converter::cmToTwip(5.00),
                        $this->setColSpanCell(9, 'DBE5F1')
                    );
                    $cellRecoRun = $cellReco->addTextRun($this->leftParagraph);
                    $cellRecoRun->addText($importance . ' ', $this->redFont);
                    $cellRecoRun->addText(_WT($recommendationRisk->getRecommendation()->getCode()), $this->boldFont);
                    $cellRecoRun->addText(
                        ' - ' . _WT($recommendationRisk->getRecommendation()->getDescription()),
                        $this->boldFont
                    );
                }

                $continue = true;

                $key = $recommendationRisk->getRecommendation()->getUuid()
                    . ' - ' . $recommendationRisk->getThreat()->getUuid()
                    . ' - ' . $recommendationRisk->getVulnerability()?->getUuid()
                    . ' - ' . $recommendationRisk->getGlobalObject()?->getUuid();
                if (isset($toUnset[$key])) {
                    if (isset($alreadySet[$key])
                        || $instanceRisk->getCacheMaxRisk() < $toUnset[$key]
                    ) {
                        $continue = false;
                    } else {
                        $alreadySet[$key] = true;
                    }
                }

                if ($continue) {
                    $path = $this->getObjectInstancePath($recommendationRisk);

                    $table->addRow(400);
                    $table->addCell(PhpWord\Shared\Converter::cmToTwip(3.00), $this->vAlignCenterCell)
                        ->addText(_WT($path), $this->normalFont, $this->leftParagraph);
                    $table->addCell(PhpWord\Shared\Converter::cmToTwip(6.00), $this->vAlignCenterCell)
                        ->addText(
                            _WT($recommendationRisk->getThreat()->getLabel($this->currentLangAnrIndex)),
                            $this->normalFont,
                            $this->leftParagraph
                        );
                    $table->addCell(PhpWord\Shared\Converter::cmToTwip(6.00), $this->vAlignCenterCell)
                        ->addText(
                            _WT($recommendationRisk->getVulnerability()?->getLabel($this->currentLangAnrIndex)),
                            $this->normalFont,
                            $this->leftParagraph
                        );
                    $table->addCell(PhpWord\Shared\Converter::cmToTwip(6.00), $this->vAlignCenterCell)
                        ->addText(_WT($instanceRisk->getComment()), $this->normalFont, $this->leftParagraph);
                    $table->addCell(
                        PhpWord\Shared\Converter::cmToTwip(0.70),
                        $this->setBgColorCell($riskConfidentiality)
                    )->addText((string)$riskConfidentiality, $this->boldFont, $this->centerParagraph);
                    $table->addCell(PhpWord\Shared\Converter::cmToTwip(0.70), $this->setBgColorCell($riskIntegrity))
                        ->addText((string)$riskIntegrity, $this->boldFont, $this->centerParagraph);
                    $table->addCell(PhpWord\Shared\Converter::cmToTwip(0.70), $this->setBgColorCell($riskAvailability))
                        ->addText((string)$riskAvailability, $this->boldFont, $this->centerParagraph);
                    $table->addCell(PhpWord\Shared\Converter::cmToTwip(2.10), $this->vAlignCenterCell)
                        ->addText(
                            $this->anrTranslate($instanceRisk->getTreatmentName()),
                            $this->normalFont,
                            $this->leftParagraph
                        );
                    $table->addCell(
                        PhpWord\Shared\Converter::cmToTwip(2.10),
                        $this->setBgColorCell($instanceRisk->getCacheTargetedRisk())
                    )->addText((string)$instanceRisk->getCacheTargetedRisk(), $this->boldFont, $this->centerParagraph);
                }
            }
            $previousRecoId = $recommendationRisk->getRecommendation()->getUuid();
        }

        return $table;
    }

    /**
     * Generates the Operational Risks Plan data
     * @return mixed|string The WordXml data generated
     */
    private function generateOperationalRisksPlan()
    {
        $recommendationRisks = $this->recommendationRiskTable->findByAnrOrderByAndCanExcludeNotTreated(
            $this->anr,
            ['r.position' => 'ASC']
        );

        $tableWord = new PhpWord\PhpWord();
        $section = $tableWord->addSection();
        $table = $section->addTable($this->borderTable);

        if (!empty($recommendationRisks)) {
            $table->addRow(400, $this->tblHeader);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(3.00), $this->grayCell)
                ->addText($this->anrTranslate('Asset'), $this->boldFont, $this->centerParagraph);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(12.20), $this->grayCell)
                ->addText($this->anrTranslate('Risk description'), $this->boldFont, $this->centerParagraph);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(6.00), $this->grayCell)
                ->addText($this->anrTranslate('Existing controls'), $this->boldFont, $this->centerParagraph);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(2.10), $this->grayCell)
                ->addText($this->anrTranslate('Current risk'), $this->boldFont, $this->centerParagraph);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(2.10), $this->grayCell)
                ->addText($this->anrTranslate('Treatment'), $this->boldFont, $this->centerParagraph);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(2.10), $this->grayCell)
                ->addText($this->anrTranslate('Residual risk'), $this->boldFont, $this->centerParagraph);
        }

        $previousRecoId = null;
        foreach ($recommendationRisks as $recommendationRisk) {
            if ($recommendationRisk->getInstanceRiskOp()) {
                $cacheNetRisk = $recommendationRisk->getInstanceRiskOp()->getCacheNetRisk() !== -1
                    ? $recommendationRisk->getInstanceRiskOp()->getCacheNetRisk()
                    : '-';
                $cacheTargetedRisk = $recommendationRisk->getInstanceRiskOp()->getCacheTargetedRisk() !== -1
                    ? $recommendationRisk->getInstanceRiskOp()->getCacheTargetedRisk()
                    : $cacheNetRisk;

                $importance = '';
                for ($i = 0; $i <= ($recommendationRisk->getRecommendation()->getImportance() - 1); $i++) {
                    $importance .= '●';
                }

                if ($recommendationRisk->getRecommendation()->getUuid() !== $previousRecoId) {
                    $table->addRow(400);
                    $cellReco = $table->addCell(
                        PhpWord\Shared\Converter::cmToTwip(5.00),
                        $this->setColSpanCell(6, 'DBE5F1')
                    );
                    $cellRecoRun = $cellReco->addTextRun($this->leftParagraph);
                    $cellRecoRun->addText($importance . ' ', $this->redFont);
                    $cellRecoRun->addText(_WT($recommendationRisk->getRecommendation()->getCode()), $this->boldFont);
                    $cellRecoRun->addText(
                        ' - ' . _WT($recommendationRisk->getRecommendation()->getDescription()),
                        $this->boldFont
                    );
                }

                $path = $this->getObjectInstancePath($recommendationRisk);

                $table->addRow(400);
                $table->addCell(PhpWord\Shared\Converter::cmToTwip(3.00), $this->vAlignCenterCell)
                    ->addText(_WT($path), $this->normalFont, $this->leftParagraph);
                $table->addCell(PhpWord\Shared\Converter::cmToTwip(12.20), $this->vAlignCenterCell)
                    ->addText(
                        _WT($recommendationRisk->getInstanceRiskOp()->getRiskCacheLabel($this->currentLangAnrIndex)),
                        $this->normalFont,
                        $this->leftParagraph
                    );
                $table->addCell(PhpWord\Shared\Converter::cmToTwip(6.00), $this->vAlignCenterCell)
                    ->addText(
                        _WT($recommendationRisk->getInstanceRiskOp()->getComment()),
                        $this->normalFont,
                        $this->leftParagraph
                    );
                $table->addCell(PhpWord\Shared\Converter::cmToTwip(2.10), $this->setBgColorCell($cacheNetRisk, false))
                    ->addText($cacheNetRisk, $this->boldFont, $this->centerParagraph);
                $table->addCell(PhpWord\Shared\Converter::cmToTwip(2.10), $this->vAlignCenterCell)
                    ->addText(
                        $this->anrTranslate($recommendationRisk->getInstanceRiskOp()->getTreatmentName()),
                        $this->normalFont,
                        $this->leftParagraph
                    );

                $table->addCell(
                    PhpWord\Shared\Converter::cmToTwip(2.10),
                    $this->setBgColorCell($cacheTargetedRisk, false)
                )->addText($cacheTargetedRisk, $this->boldFont, $this->centerParagraph);

                $previousRecoId = $recommendationRisk->getRecommendation()->getUuid();
            }
        }

        return $table;
    }

    /**
     * Generates the Implamentation Recommendations Plan data
     * @return mixed|string The WordXml data generated
     */
    private function generateTableImplementationPlan()
    {
        $recommendationRisks = $this->recommendationRiskTable->findByAnrOrderByAndCanExcludeNotTreated(
            $this->anr,
            ['r.position' => 'ASC']
        );

        $tableWord = new PhpWord\PhpWord();
        $section = $tableWord->addSection();
        $table = $section->addTable($this->borderTable);

        if (!empty($recommendationRisks)) {
            $table->addRow(400, $this->tblHeader);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(10.00), $this->grayCell)
                ->addText($this->anrTranslate('Recommendation'), $this->boldFont, $this->centerParagraph);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(2.00), $this->grayCell)
                ->addText($this->anrTranslate('Imp.'), $this->boldFont, $this->centerParagraph);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(5.00), $this->grayCell)
                ->addText($this->anrTranslate('Comment'), $this->boldFont, $this->centerParagraph);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(4.00), $this->grayCell)
                ->addText($this->anrTranslate('Manager'), $this->boldFont, $this->centerParagraph);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(3.00), $this->grayCell)
                ->addText($this->anrTranslate('Deadline'), $this->boldFont, $this->centerParagraph);
        }

        $globalObjectsRecommendationsKeys = [];
        foreach ($recommendationRisks as $recommendationRisk) {
            $recommendation = $recommendationRisk->getRecommendation();
            if ($recommendationRisk->hasGlobalObjectRelation()) {
                $key = 'o' . $recommendationRisk->getGlobalObject()->getUuid()
                    . '-' . $recommendationRisk->getInstanceRisk()->getThreat()->getUuid()
                    . '-' . $recommendationRisk->getInstanceRisk()->getVulnerability()->getUuid()
                    . '-' . $recommendation->getUuid();
                if (isset($globalObjectsRecommendationsKeys[$key])) {
                    continue;
                }
                $globalObjectsRecommendationsKeys[$key] = $key;
            }

            $importance = '';
            for ($i = 0; $i <= ($recommendation->getImportance() - 1); $i++) {
                $importance .= '●';
            }

            $recoDeadline = '';
            if ($recommendation->getDueDate() !== null) {
                $recoDeadline = $recommendation->getDueDate()->format('d-m-Y');
            }

            $table->addRow(400);
            $cellRecoName = $table->addCell(PhpWord\Shared\Converter::cmToTwip(5.00), $this->vAlignCenterCell);
            $cellRecoNameRun = $cellRecoName->addTextRun($this->leftParagraph);
            $cellRecoNameRun->addText(_WT($recommendation->getCode()) . '<w:br/>', $this->boldFont);
            $cellRecoNameRun->addText(_WT($recommendation->getDescription()), $this->normalFont);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(2.00), $this->vAlignCenterCell)
                ->addText($importance, $this->redFont, $this->centerParagraph);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(5.00), $this->vAlignCenterCell)
                ->addText(_WT($recommendation->getComment()), $this->normalFont, $this->leftParagraph);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(4.00), $this->vAlignCenterCell)
                ->addText(_WT($recommendation->getResponsible()), $this->normalFont, $this->centerParagraph);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(3.00), $this->vAlignCenterCell)
                ->addText($recoDeadline, $this->normalFont, $this->centerParagraph);
        }

        return $table;
    }

    /**
     * Generates the Implamentation Recommendations Plan data
     * @return mixed|string The WordXml data generated
     */
    private function generateTableImplementationHistory()
    {
        /** @var Entity\RecommendationHistory[] $recoRecords */
        $recoRecords = $this->recommendationHistoryTable->findByAnr($this->anr);

        $tableWord = new PhpWord\PhpWord();
        $section = $tableWord->addSection();
        $table = $section->addTable($this->borderTable);

        if ($recoRecords) {
            $table->addRow(400, $this->tblHeader);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(3.00), $this->grayCell)
                ->addText($this->anrTranslate('By'), $this->boldFont, $this->centerParagraph);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(6.00), $this->grayCell)
                ->addText($this->anrTranslate('Recommendation'), $this->boldFont, $this->centerParagraph);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(8.00), $this->grayCell)
                ->addText($this->anrTranslate('Risk'), $this->boldFont, $this->centerParagraph);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(4.50), $this->grayCell)
                ->addText($this->anrTranslate('Implementation comment'), $this->boldFont, $this->centerParagraph);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(1.75), $this->grayCell)
                ->addText($this->anrTranslate('Risk before'), $this->boldFont, $this->centerParagraph);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(1.75), $this->grayCell)
                ->addText($this->anrTranslate('Risk after'), $this->boldFont, $this->centerParagraph);
        }

        foreach ($recoRecords as $recoRecord) {
            $importance = '';
            for ($i = 0; $i <= ($recoRecord->getRecoImportance() - 1); $i++) {
                $importance .= '●';
            }

            $recoDeadline = $recoRecord->getRecoDueDate() === null
                ? ''
                : $recoRecord->getRecoDueDate()->format('d/m/Y');

            $recoValidationDate = $recoRecord->getCreatedAt()->format('d/m/Y');

            $riskMaxBefore = $recoRecord->getRiskMaxRiskBefore();
            $bgcolorRiskBefore = 'FD661F';
            if ($recoRecord->getRiskColorBefore() === 'green') {
                $bgcolorRiskBefore = 'D6F107';
            } elseif ($recoRecord->getRiskColorBefore() === 'orange') {
                $bgcolorRiskBefore = 'FFBC1C';
            } elseif ($riskMaxBefore === -1) {
                $riskMaxBefore = '-';
                $bgcolorRiskBefore = 'FFFFFF';
            }
            $styleContentCellRiskBefore = ['valign' => 'center', 'bgcolor' => $bgcolorRiskBefore];

            $riskMaxAfter = $recoRecord->getRiskMaxRiskAfter();
            $bgcolorRiskAfter = 'FD661F';
            if ($recoRecord->getRiskColorAfter() === 'green') {
                $bgcolorRiskAfter = 'D6F107';
            } elseif ($recoRecord->getRiskColorAfter() === 'orange') {
                $bgcolorRiskAfter = 'FFBC1C';
            } elseif ($riskMaxAfter === -1) {
                $riskMaxAfter = '-';
                $bgcolorRiskAfter = 'FFFFFF';
            }
            $styleContentCellRiskAfter = ['valign' => 'center', 'bgcolor' => $bgcolorRiskAfter];

            $table->addRow(400);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(3.00), $this->vAlignCenterCell)
                ->addText(_WT($recoRecord->creator), $this->normalFont, $this->leftParagraph);
            $cellReco = $table->addCell(PhpWord\Shared\Converter::cmToTwip(6.00), $this->vAlignCenterCell);
            $cellRecoRun = $cellReco->addTextRun($this->leftParagraph);
            $cellRecoRun->addText($importance . ' ', $this->redFont);
            $cellRecoRun->addText(_WT($recoRecord->getRecoCode()) . '<w:br/>', $this->boldFont);
            $cellRecoRun->addText(_WT($recoRecord->getRecoDescription()) . '<w:br/>' . '<w:br/>', $this->normalFont);
            $cellRecoRun->addText($this->anrTranslate('Comment') . ': ', $this->boldFont);
            $cellRecoRun->addText(_WT($recoRecord->getRecoComment()) . '<w:br/>', $this->normalFont);
            $cellRecoRun->addText($this->anrTranslate('Deadline') . ': ', $this->boldFont);
            $cellRecoRun->addText($recoDeadline . '<w:br/>', $this->normalFont);
            $cellRecoRun->addText($this->anrTranslate('Validation date') . ': ', $this->boldFont);
            $cellRecoRun->addText($recoValidationDate . '<w:br/>', $this->normalFont);
            $cellRecoRun->addText($this->anrTranslate('Manager') . ': ', $this->boldFont);
            $cellRecoRun->addText(_WT($recoRecord->getRecoResponsable()), $this->normalFont);
            $cellRisk = $table->addCell(PhpWord\Shared\Converter::cmToTwip(8.00), $this->vAlignCenterCell);
            $cellRiskRun = $cellRisk->addTextRun($this->leftParagraph);
            $cellRiskRun->addText($this->anrTranslate('Asset type') . ': ', $this->boldFont);
            $cellRiskRun->addText(_WT($recoRecord->getRiskAsset()) . '<w:br/>', $this->normalFont);
            $cellRiskRun->addText($this->anrTranslate('Asset') . ': ', $this->boldFont);
            $cellRiskRun->addText(_WT($recoRecord->getRiskInstance()) . '<w:br/>', $this->normalFont);
            $cellRiskRun->addText($this->anrTranslate('Threat') . ': ', $this->boldFont);
            $cellRiskRun->addText(_WT($recoRecord->getRiskThreat()) . '<w:br/>', $this->normalFont);
            $cellRiskRun->addText($this->anrTranslate('Vulnerability') . ': ', $this->boldFont);
            $cellRiskRun->addText(_WT($recoRecord->getRiskVul()) . '<w:br/>', $this->normalFont);
            $cellRiskRun->addText($this->anrTranslate('Treatment type') . ': ', $this->boldFont);
            $cellRiskRun->addText(
                InstanceRiskSuperClass::getTreatmentNameByType($recoRecord->getRiskKindOfMeasure()) . '<w:br/>',
                $this->normalFont
            );
            $cellRiskRun->addText($this->anrTranslate('Existing controls') . ': ', $this->boldFont);
            $cellRiskRun->addText(_WT($recoRecord->getRiskCommentBefore()) . '<w:br/>', $this->normalFont);
            $cellRiskRun->addText($this->anrTranslate('New controls') . ': ', $this->boldFont);
            $cellRiskRun->addText(_WT($recoRecord->getRiskCommentAfter()) . '<w:br/>', $this->normalFont);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(4.50), $this->vAlignCenterCell)
                ->addText(_WT($recoRecord->getImplComment()), $this->normalFont, $this->leftParagraph);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(1.75), $styleContentCellRiskBefore)
                ->addText($riskMaxBefore, $this->boldFont, $this->centerParagraph);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(1.75), $styleContentCellRiskAfter)
                ->addText($riskMaxAfter, $this->boldFont, $this->centerParagraph);
        }

        return $table;
    }

    /**
     * Generates the Statement Of Applicability Scale.
     *
     * @param Entity\SoaScaleComment[] $soaScaleComments
     */
    private function generateTableStatementOfApplicabilityScale(array $soaScaleComments): PhpWord\Element\Table
    {
        $tableWord = new PhpWord\PhpWord();
        $section = $tableWord->addSection();
        $table = $section->addTable($this->borderTable);
        $noBorderCell = [
            'borderTopColor' => 'FFFFFF',
            'borderTopSize' => 0,
            'borderLeftColor' => 'FFFFFF',
            'borderLeftSize' => 0,
        ];

        if (!empty($soaScaleComments)) {
            $table->addRow(400, $this->tblHeader);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(2.00), $noBorderCell);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(8.00), $this->grayCell)
                ->addText($this->anrTranslate('Level of compliance'), $this->boldFont, $this->centerParagraph);

            foreach ($soaScaleComments as $comment) {
                $table->addRow(400);
                $table->addCell(PhpWord\Shared\Converter::cmToTwip(2.00), $this->vAlignCenterCell)
                    ->addText((string)$comment->getScaleIndex(), $this->normalFont, $this->centerParagraph);
                $this->customizableCell['BgColor'] = $comment->getColour();
                $table->addCell(PhpWord\Shared\Converter::cmToTwip(8.00), $this->customizableCell)
                    ->addText(_WT($comment->getComment()), $this->normalFont, $this->leftParagraph);
            }
        }

        return $table;
    }

    /**
     * Generates the Statement Of Applicability data
     */
    private function generateTableStatementOfApplicability(string $referentialUuid): PhpWord\Element\Table
    {
        $measures = $this->measureTable->findByAnrAndReferentialUuidOrderByCode($this->anr, $referentialUuid);

        /* Create section. */
        $tableWord = new PhpWord\PhpWord();
        $section = $tableWord->addSection();
        $table = $section->addTable($this->borderTable);

        $inclusionsTranslations = [
            'EX' => $this->anrTranslate('Excluded'),
            'LR' => $this->anrTranslate('Legal requirements'),
            'CO' => $this->anrTranslate('Contractual obligations'),
            'BR' => $this->anrTranslate('Business requirements'),
            'BP' => $this->anrTranslate('Best practices'),
            'RRA' => $this->anrTranslate('Results of risk assessment'),
        ];
        $previousCatId = null;
        $isTitleSet = false;
        foreach ($measures as $measure) {
            $soa = $measure->getSoa();
            if ($soa === null) {
                continue;
            }

            if (!$isTitleSet) {
                $table->addRow(400, $this->tblHeader);
                $table->addCell(PhpWord\Shared\Converter::cmToTwip(1.00), $this->grayCell)
                    ->addText($this->anrTranslate('Code'), $this->boldFont, $this->centerParagraph);
                $table->addCell(PhpWord\Shared\Converter::cmToTwip(5.00), $this->grayCell)
                    ->addText($this->anrTranslate('Control'), $this->boldFont, $this->centerParagraph);
                $table->addCell(PhpWord\Shared\Converter::cmToTwip(4.00), $this->grayCell)
                    ->addText($this->anrTranslate('Inclusion/Exclusion'), $this->boldFont, $this->centerParagraph);
                $table->addCell(PhpWord\Shared\Converter::cmToTwip(5.00), $this->grayCell)
                    ->addText($this->anrTranslate('Remarks/Justification'), $this->boldFont, $this->centerParagraph);
                $table->addCell(PhpWord\Shared\Converter::cmToTwip(5.00), $this->grayCell)
                    ->addText($this->anrTranslate('Evidences'), $this->boldFont, $this->centerParagraph);
                $table->addCell(PhpWord\Shared\Converter::cmToTwip(5.00), $this->grayCell)
                    ->addText($this->anrTranslate('Actions'), $this->boldFont, $this->centerParagraph);
                $table->addCell(PhpWord\Shared\Converter::cmToTwip(2.00), $this->grayCell)
                    ->addText($this->anrTranslate('Level of compliance'), $this->boldFont, $this->centerParagraph);
                $isTitleSet = true;
            }

            $inclusions = [];
            if ($soa->getEx()) {
                $inclusions[] = $inclusionsTranslations['EX'];
            }
            if ($soa->getLr()) {
                $inclusions[] = $inclusionsTranslations['LR'];
            }
            if ($soa->getCo()) {
                $inclusions[] = $inclusionsTranslations['CO'];
            }
            if ($soa->getBr()) {
                $inclusions[] = $inclusionsTranslations['BR'];
            }
            if ($soa->getBp()) {
                $inclusions[] = $inclusionsTranslations['BP'];
            }
            if ($soa->getRra()) {
                $inclusions[] = $inclusionsTranslations['RRA'];
            }
            $inclusion = implode("\n\n", $inclusions);

            $complianceLevel = "";
            $bgcolor = 'FFFFFF';

            $soaScaleComment = $soa->getSoaScaleComment();
            if ($soaScaleComment !== null && !$soaScaleComment->isHidden()) {
                $complianceLevel = $soaScaleComment->getComment();
                $bgcolor = $soaScaleComment->getColour();
            }

            if ($soa->getEx()) {
                $complianceLevel = "";
                $bgcolor = 'E7E6E6';
            }

            $styleContentCellCompliance = ['valign' => 'center', 'bgcolor' => $bgcolor];

            if ($measure->getCategory() !== null && $measure->getCategory()->getId() !== $previousCatId) {
                $table->addRow(400);
                $table->addCell(PhpWord\Shared\Converter::cmToTwip(10.00), $this->setColSpanCell(7, 'DBE5F1'))
                    ->addText(
                        _WT($measure->getCategory()->getLabel($this->currentLangAnrIndex)),
                        $this->boldFont,
                        $this->leftParagraph
                    );
                $previousCatId = $measure->getCategory()->getId();
            }

            $table->addRow(400);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(1.00), $this->vAlignCenterCell)
                ->addText(_WT($measure->getCode()), $this->normalFont, $this->centerParagraph);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(5.00), $this->vAlignCenterCell)
                ->addText(
                    _WT($measure->getLabel($this->currentLangAnrIndex)),
                    $this->normalFont,
                    $this->leftParagraph
                );
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(4.00), $this->vAlignCenterCell)
                ->addText(_WT($inclusion), $this->normalFont, $this->leftParagraph);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(5.00), $this->vAlignCenterCell)
                ->addText(_WT($soa->getRemarks()), $this->normalFont, $this->leftParagraph);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(5.00), $this->vAlignCenterCell)
                ->addText(_WT($soa->getEvidences()), $this->normalFont, $this->leftParagraph);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(5.00), $this->vAlignCenterCell)
                ->addText(_WT($soa->getActions()), $this->normalFont, $this->leftParagraph);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(2.00), $styleContentCellCompliance)
                ->addText(_WT($complianceLevel), $this->normalFont, $this->centerParagraph);
        }

        return $table;
    }

    /**
     * Generates the table risks by control in SOA.
     *
     * @return mixed|string The WordXml data generated
     */
    private function generateTableRisksByControl(string $referentialUuid)
    {
        $opRisksAllScales = $this->operationalRiskScaleService->getOperationalRiskScales($this->anr);
        $opRisksImpactsScaleType = array_values(
            array_filter($opRisksAllScales, static function ($scale) {
                return $scale['type'] === OperationalRiskScaleSuperClass::TYPE_IMPACT;
            })
        );
        $opRisksImpactsScales = array_filter($opRisksImpactsScaleType[0]['scaleTypes'], static function ($scale) {
            return !$scale['isHidden'];
        });
        $sizeCellImpact = count($opRisksImpactsScales) * 0.70;

        //create section
        $tableWord = new PhpWord\PhpWord();
        $section = $tableWord->addSection();

        $measures = $this->measureTable->findByAnrAndReferentialUuidOrderByCode($this->anr, $referentialUuid);
        $previousMeasureUuid = null;
        foreach ($measures as $measure) {
            $soa = $measure->getSoa();
            if ($soa === null) {
                continue;
            }

            $amvUuids = [];
            $rolfRiskIds = [];
            foreach ($measure->getAmvs() as $amv) {
                $amvUuids[] = $amv->getUuid();
            }
            foreach ($measure->getRolfRisks() as $rolfRisk) {
                $rolfRiskIds[] = $rolfRisk->getId();
            }
            $instanceRisks = [];
            $operationalInstanceRisks = [];
            if (!empty($amvUuids)) {
                $instanceRisks = $this->anrInstanceRiskService->getInstanceRisks(
                    $this->anr,
                    null,
                    ['amvs' => $amvUuids, 'limit' => -1, 'order' => 'maxRisk', 'order_direction' => 'desc']
                );
            }
            if (!empty($rolfRiskIds)) {
                $operationalInstanceRisks = $this->anrInstanceRiskOpService->getOperationalRisks(
                    $this->anr,
                    null,
                    ['rolfRisks' => $rolfRiskIds, 'limit' => -1, 'order' => 'cacheNetRisk', 'order_direction' => 'desc']
                );
            }

            if (!empty($instanceRisks) || !empty($operationalInstanceRisks)) {
                if ($measure->getUuid() !== $previousMeasureUuid) {
                    $section->addText(
                        _WT($measure->getCode()) . ' - ' . _WT($measure->getLabel($this->currentLangAnrIndex)),
                        array_merge($this->boldFont, ['size' => 11])
                    );

                    if (!empty($instanceRisks)) {
                        $section->addText($this->anrTranslate('Information risks'), $this->boldFont);
                        $tableRiskInfo = $section->addTable($this->borderTable);

                        $tableRiskInfo->addRow(400);
                        $tableRiskInfo->addCell(PhpWord\Shared\Converter::cmToTwip(3.00), $this->restartAndGrayCell)
                            ->addText($this->anrTranslate('Asset'), $this->boldFont, $this->centerParagraph);
                        $tableRiskInfo->addCell(
                            PhpWord\Shared\Converter::cmToTwip(2.10),
                            $this->setColSpanCell(3, 'DFDFDF')
                        )->addText($this->anrTranslate('Impact'), $this->boldFont, $this->centerParagraph);
                        $tableRiskInfo->addCell(
                            PhpWord\Shared\Converter::cmToTwip(4.50),
                            $this->setColSpanCell(2, 'DFDFDF')
                        )->addText($this->anrTranslate('Threat'), $this->boldFont, $this->centerParagraph);
                        $tableRiskInfo->addCell(
                            PhpWord\Shared\Converter::cmToTwip(10.00),
                            $this->setColSpanCell(3, 'DFDFDF')
                        )->addText($this->anrTranslate('Vulnerability'), $this->boldFont, $this->centerParagraph);
                        $tableRiskInfo->addCell(
                            PhpWord\Shared\Converter::cmToTwip(3.00),
                            $this->setColSpanCell(3, 'DFDFDF')
                        )->addText($this->anrTranslate('Current risk'), $this->boldFont, $this->centerParagraph);
                        $tableRiskInfo->addCell(PhpWord\Shared\Converter::cmToTwip(3.00), $this->restartAndGrayCell)
                            ->addText($this->anrTranslate('Treatment'), $this->boldFont, $this->centerParagraph);
                        $tableRiskInfo->addCell(PhpWord\Shared\Converter::cmToTwip(1.50), $this->restartAndGrayCell)
                            ->addText($this->anrTranslate('Residual risk'), $this->boldFont, $this->centerParagraph);
                        $tableRiskInfo->addRow(400);
                        $tableRiskInfo->addCell(PhpWord\Shared\Converter::cmToTwip(3.00), $this->continueAndGrayCell);
                        $tableRiskInfo->addCell(PhpWord\Shared\Converter::cmToTwip(0.70), $this->grayCell)
                            ->addText('C', $this->boldFont, $this->centerParagraph);
                        $tableRiskInfo->addCell(PhpWord\Shared\Converter::cmToTwip(0.70), $this->grayCell)
                            ->addText('I', $this->boldFont, $this->centerParagraph);
                        $tableRiskInfo->addCell(PhpWord\Shared\Converter::cmToTwip(0.70), $this->grayCell)
                            ->addText($this->anrTranslate('A'), $this->boldFont, $this->centerParagraph);
                        $tableRiskInfo->addCell(PhpWord\Shared\Converter::cmToTwip(2.50), $this->grayCell)
                            ->addText($this->anrTranslate('Label'), $this->boldFont, $this->centerParagraph);
                        $tableRiskInfo->addCell(PhpWord\Shared\Converter::cmToTwip(2.00), $this->grayCell)
                            ->addText($this->anrTranslate('Prob.'), $this->boldFont, $this->centerParagraph);
                        $tableRiskInfo->addCell(PhpWord\Shared\Converter::cmToTwip(3.00), $this->grayCell)
                            ->addText($this->anrTranslate('Label'), $this->boldFont, $this->centerParagraph);
                        $tableRiskInfo->addCell(PhpWord\Shared\Converter::cmToTwip(4.00), $this->grayCell)
                            ->addText(
                                $this->anrTranslate('Existing controls'),
                                $this->boldFont,
                                $this->centerParagraph
                            );
                        $tableRiskInfo->addCell(PhpWord\Shared\Converter::cmToTwip(3.00), $this->grayCell)
                            ->addText($this->anrTranslate('Qualif.'), $this->boldFont, $this->centerParagraph);
                        $tableRiskInfo->addCell(PhpWord\Shared\Converter::cmToTwip(1.00), $this->grayCell)
                            ->addText('C', $this->boldFont, $this->centerParagraph);
                        $tableRiskInfo->addCell(PhpWord\Shared\Converter::cmToTwip(1.00), $this->grayCell)
                            ->addText('I', $this->boldFont, $this->centerParagraph);
                        $tableRiskInfo->addCell(PhpWord\Shared\Converter::cmToTwip(1.00), $this->grayCell)
                            ->addText($this->anrTranslate('A'), $this->boldFont, $this->centerParagraph);
                        $tableRiskInfo->addCell(PhpWord\Shared\Converter::cmToTwip(3.00), $this->continueAndGrayCell);
                        $tableRiskInfo->addCell(PhpWord\Shared\Converter::cmToTwip(1.50), $this->continueAndGrayCell);
                    }
                    if (!empty($operationalInstanceRisks)) {
                        $section->addText($this->anrTranslate('Operational risks'), $this->boldFont);
                        $tableRiskOp = $section->addTable($this->borderTable);

                        $tableRiskOp->addRow(400);
                        $tableRiskOp->addCell(PhpWord\Shared\Converter::cmToTwip(3.00), $this->restartAndGrayCell)
                            ->addText($this->anrTranslate('Asset'), $this->boldFont, $this->centerParagraph);
                        $tableRiskOp->addCell(PhpWord\Shared\Converter::cmToTwip(10.00), $this->restartAndGrayCell)
                            ->addText($this->anrTranslate('Risk description'), $this->boldFont, $this->centerParagraph);
                        if ($this->anr->showRolfBrut()) {
                            $tableRiskOp->addCell(
                                PhpWord\Shared\Converter::cmToTwip(5.50),
                                $this->setColSpanCell(2 + count($opRisksImpactsScales), 'DFDFDF')
                            )->addText($this->anrTranslate('Inherent risk'), $this->boldFont, $this->centerParagraph);
                        }
                        $tableRiskOp->addCell(
                            PhpWord\Shared\Converter::cmToTwip(15.00),
                            $this->setColSpanCell(3 + count($opRisksImpactsScales), 'DFDFDF')
                        )->addText($this->anrTranslate('Net risk'), $this->boldFont, $this->centerParagraph);
                        $tableRiskOp->addCell(PhpWord\Shared\Converter::cmToTwip(2.00), $this->restartAndGrayCell)
                            ->addText($this->anrTranslate('Treatment'), $this->boldFont, $this->centerParagraph);
                        $tableRiskOp->addCell(PhpWord\Shared\Converter::cmToTwip(2.00), $this->restartAndGrayCell)
                            ->addText($this->anrTranslate('Residual risk'), $this->boldFont, $this->centerParagraph);

                        $tableRiskOp->addRow(400, $this->tblHeader);
                        $tableRiskOp->addCell(PhpWord\Shared\Converter::cmToTwip(3.00), $this->continueAndGrayCell);
                        $tableRiskOp->addCell(PhpWord\Shared\Converter::cmToTwip(10.00), $this->continueAndGrayCell);
                        if ($this->anr->showRolfBrut()) {
                            $tableRiskOp->addCell(PhpWord\Shared\Converter::cmToTwip(1.00), $this->restartAndGrayCell)
                                ->addText($this->anrTranslate('Prob.'), $this->boldFont, $this->centerParagraph);
                            $tableRiskOp->addCell(
                                PhpWord\Shared\Converter::cmToTwip($sizeCellImpact),
                                $this->setColSpanCell(count($opRisksImpactsScales), 'DFDFDF')
                            )->addText($this->anrTranslate('Impact'), $this->boldFont, $this->centerParagraph);
                            $tableRiskOp->addCell(PhpWord\Shared\Converter::cmToTwip(1.00), $this->restartAndGrayCell)
                                ->addText($this->anrTranslate('Current risk'), $this->boldFont, $this->centerParagraph);
                        }
                        $tableRiskOp->addCell(PhpWord\Shared\Converter::cmToTwip(1.00), $this->restartAndGrayCell)
                            ->addText($this->anrTranslate('Prob.'), $this->boldFont, $this->centerParagraph);
                        $tableRiskOp->addCell(
                            PhpWord\Shared\Converter::cmToTwip($sizeCellImpact),
                            $this->setColSpanCell(count($opRisksImpactsScales), 'DFDFDF')
                        )->addText($this->anrTranslate('Impact'), $this->boldFont, $this->centerParagraph);
                        $tableRiskOp->addCell(PhpWord\Shared\Converter::cmToTwip(1.00), $this->restartAndGrayCell)
                            ->addText($this->anrTranslate('Current risk'), $this->boldFont, $this->centerParagraph);
                        $tableRiskOp->addCell(PhpWord\Shared\Converter::cmToTwip(8.00), $this->restartAndGrayCell)
                            ->addText(
                                $this->anrTranslate('Existing controls'),
                                $this->boldFont,
                                $this->centerParagraph
                            );
                        $tableRiskOp->addCell(PhpWord\Shared\Converter::cmToTwip(2.00), $this->continueAndGrayCell);
                        $tableRiskOp->addCell(PhpWord\Shared\Converter::cmToTwip(2.00), $this->continueAndGrayCell);

                        $tableRiskOp->addRow(PhpWord\Shared\Converter::cmToTwip(1.00), ['tblHeader' => true]);
                        $tableRiskOp->addCell(PhpWord\Shared\Converter::cmToTwip(3.00), $this->continueAndGrayCell);
                        $tableRiskOp->addCell(PhpWord\Shared\Converter::cmToTwip(10.00), $this->continueAndGrayCell);
                        if ($this->anr->showRolfBrut()) {
                            $tableRiskOp->addCell(PhpWord\Shared\Converter::cmToTwip(1.00), $this->continueAndGrayCell);
                            foreach ($opRisksImpactsScales as $opRiskImpactScale) {
                                $label = mb_substr(_WT($opRiskImpactScale['label']), 0, 3) . '.';
                                $tableRiskOp->addCell(
                                    PhpWord\Shared\Converter::cmToTwip(0.70),
                                    array_merge($this->rotate90TextCell, ['bgcolor' => 'DFDFDF'])
                                )->addText($label, $this->boldFont, $this->verticalCenterParagraph);
                            }
                            $tableRiskOp->addCell(PhpWord\Shared\Converter::cmToTwip(1.00), $this->continueAndGrayCell);
                        }
                        $tableRiskOp->addCell(PhpWord\Shared\Converter::cmToTwip(1.00), $this->continueAndGrayCell);
                        foreach ($opRisksImpactsScales as $opRiskImpactScale) {
                            $label = mb_substr(_WT($opRiskImpactScale['label']), 0, 3) . '.';
                            $tableRiskOp->addCell(
                                PhpWord\Shared\Converter::cmToTwip(0.70),
                                array_merge($this->rotate90TextCell, ['bgcolor' => 'DFDFDF'])
                            )->addText($label, $this->boldFont, $this->verticalCenterParagraph);
                        }
                        $tableRiskOp->addCell(PhpWord\Shared\Converter::cmToTwip(1.00), $this->continueAndGrayCell);
                        $tableRiskOp->addCell(PhpWord\Shared\Converter::cmToTwip(8.00), $this->continueAndGrayCell);
                        $tableRiskOp->addCell(PhpWord\Shared\Converter::cmToTwip(2.00), $this->continueAndGrayCell);
                        $tableRiskOp->addCell(PhpWord\Shared\Converter::cmToTwip(2.00), $this->continueAndGrayCell);
                    }
                }

                $previousMeasureUuid = $measure->getUuid();
                if (!empty($instanceRisks)) {
                    $impacts = ['c', 'i', 'd'];

                    foreach ($instanceRisks as $instanceRisk) {
                        foreach ($impacts as $impact) {
                            if ($instanceRisk[$impact . '_risk_enabled'] === 0) {
                                $instanceRisk[$impact . '_risk'] = null;
                            }
                        }

                        foreach ($instanceRisk as $key => $value) {
                            if ($value === -1) {
                                $instanceRisk[$key] = '-';
                            }
                        }

                        /** @var Entity\Instance $instance */
                        $instance = $this->instanceTable->findByIdAndAnr($instanceRisk['instance'], $this->anr);
                        if (!$instance->getObject()->isScopeGlobal()) {
                            $path = $instance->getHierarchyString();
                        } else {
                            $path = $instance->getName($this->currentLangAnrIndex)
                                . ' (' . $this->anrTranslate('Global') . ')';
                        }

                        $tableRiskInfo->addRow(400);
                        $tableRiskInfo->addCell(PhpWord\Shared\Converter::cmToTwip(3.00), $this->vAlignCenterCell)
                            ->addText(_WT($path), $this->normalFont, $this->leftParagraph);
                        $tableRiskInfo->addCell(PhpWord\Shared\Converter::cmToTwip(0.70), $this->vAlignCenterCell)
                            ->addText($instanceRisk['c_impact'], $this->normalFont, $this->centerParagraph);
                        $tableRiskInfo->addCell(PhpWord\Shared\Converter::cmToTwip(0.70), $this->vAlignCenterCell)
                            ->addText($instanceRisk['i_impact'], $this->normalFont, $this->centerParagraph);
                        $tableRiskInfo->addCell(PhpWord\Shared\Converter::cmToTwip(0.70), $this->vAlignCenterCell)
                            ->addText($instanceRisk['d_impact'], $this->normalFont, $this->centerParagraph);
                        $tableRiskInfo->addCell(PhpWord\Shared\Converter::cmToTwip(2.50), $this->vAlignCenterCell)
                            ->addText(
                                _WT($instanceRisk['threatLabel' . $this->currentLangAnrIndex]),
                                $this->normalFont,
                                $this->leftParagraph
                            );
                        $tableRiskInfo->addCell(PhpWord\Shared\Converter::cmToTwip(2.00), $this->vAlignCenterCell)
                            ->addText($instanceRisk['threatRate'], $this->normalFont, $this->centerParagraph);
                        $tableRiskInfo->addCell(PhpWord\Shared\Converter::cmToTwip(3.00), $this->vAlignCenterCell)
                            ->addText(
                                _WT($instanceRisk['vulnLabel' . $this->currentLangAnrIndex]),
                                $this->normalFont,
                                $this->leftParagraph
                            );
                        $tableRiskInfo->addCell(PhpWord\Shared\Converter::cmToTwip(4.00), $this->vAlignCenterCell)
                            ->addText(_WT($instanceRisk['comment']), $this->normalFont, $this->leftParagraph);
                        $tableRiskInfo->addCell(PhpWord\Shared\Converter::cmToTwip(3.00), $this->vAlignCenterCell)
                            ->addText($instanceRisk['vulnerabilityRate'], $this->normalFont, $this->centerParagraph);
                        $tableRiskInfo->addCell(
                            PhpWord\Shared\Converter::cmToTwip(1.00),
                            $this->setBgColorCell($instanceRisk['c_risk'])
                        )->addText($instanceRisk['c_risk'], $this->boldFont, $this->centerParagraph);
                        $tableRiskInfo->addCell(
                            PhpWord\Shared\Converter::cmToTwip(1.00),
                            $this->setBgColorCell($instanceRisk['i_risk'])
                        )->addText($instanceRisk['i_risk'], $this->boldFont, $this->centerParagraph);
                        $tableRiskInfo->addCell(
                            PhpWord\Shared\Converter::cmToTwip(1.00),
                            $this->setBgColorCell($instanceRisk['d_risk'])
                        )->addText($instanceRisk['d_risk'], $this->boldFont, $this->centerParagraph);
                        $tableRiskInfo->addCell(PhpWord\Shared\Converter::cmToTwip(3.00), $this->vAlignCenterCell)
                            ->addText(
                                InstanceRiskOpSuperClass::getTreatmentNameByType($instanceRisk['kindOfMeasure']),
                                $this->normalFont,
                                $this->leftParagraph
                            );
                        $tableRiskInfo->addCell(
                            PhpWord\Shared\Converter::cmToTwip(1.50),
                            $this->setBgColorCell($instanceRisk['target_risk'])
                        )->addText($instanceRisk['target_risk'], $this->boldFont, $this->centerParagraph);
                    }
                }

                if (!empty($operationalInstanceRisks)) {
                    foreach ($operationalInstanceRisks as $riskOp) {
                        foreach ($riskOp as $key => $value) {
                            if ($value === -1) {
                                $riskOp[$key] = '-';
                            }
                        }

                        $instance = $this->instanceTable->findById($riskOp['instanceInfos']['id']);
                        $path = $instance->getHierarchyString();

                        $tableRiskOp->addRow(400);
                        $tableRiskOp->addCell(PhpWord\Shared\Converter::cmToTwip(3.00), $this->vAlignCenterCell)
                            ->addText(_WT($path), $this->normalFont, $this->leftParagraph);
                        $tableRiskOp->addCell(PhpWord\Shared\Converter::cmToTwip(10.00), $this->vAlignCenterCell)
                            ->addText(
                                _WT($riskOp['label' . $this->currentLangAnrIndex]),
                                $this->normalFont,
                                $this->leftParagraph
                            );
                        if ($this->anr->showRolfBrut()) {
                            $tableRiskOp->addCell(PhpWord\Shared\Converter::cmToTwip(1.00), $this->vAlignCenterCell)
                                ->addText($riskOp['brutProb'], $this->normalFont, $this->centerParagraph);
                            foreach ($opRisksImpactsScales as $opRiskImpactScale) {
                                $tableRiskOp->addCell(PhpWord\Shared\Converter::cmToTwip(0.70), $this->vAlignCenterCell)
                                    ->addText(
                                        $riskOp['scales'][$opRiskImpactScale['id']]['brutValue'] !== -1 ?
                                            $riskOp['scales'][$opRiskImpactScale['id']]['brutValue'] :
                                            '-',
                                        $this->normalFont,
                                        $this->centerParagraph
                                    );
                            }
                            $tableRiskOp->addCell(
                                PhpWord\Shared\Converter::cmToTwip(1.00),
                                $this->setBgColorCell($riskOp['cacheBrutRisk'], false)
                            )->addText($riskOp['cacheBrutRisk'], $this->boldFont, $this->centerParagraph);
                        }
                        $tableRiskOp->addCell(PhpWord\Shared\Converter::cmToTwip(1.00), $this->vAlignCenterCell)
                            ->addText($riskOp['netProb'], $this->normalFont, $this->centerParagraph);
                        foreach ($opRisksImpactsScales as $opRiskImpactScale) {
                            $tableRiskOp->addCell(PhpWord\Shared\Converter::cmToTwip(0.70), $this->vAlignCenterCell)
                                ->addText(
                                    $riskOp['scales'][$opRiskImpactScale['id']]['netValue'] !== -1
                                        ? $riskOp['scales'][$opRiskImpactScale['id']]['netValue']
                                        : '-',
                                    $this->normalFont,
                                    $this->centerParagraph
                                );
                        }
                        $tableRiskOp->addCell(
                            PhpWord\Shared\Converter::cmToTwip(1.00),
                            $this->setBgColorCell($riskOp['cacheNetRisk'], false)
                        )->addText($riskOp['cacheNetRisk'], $this->boldFont, $this->centerParagraph);
                        $tableRiskOp->addCell(PhpWord\Shared\Converter::cmToTwip(8.00), $this->vAlignCenterCell)
                            ->addText(_WT($riskOp['comment']), $this->normalFont, $this->leftParagraph);
                        $tableRiskOp->addCell(PhpWord\Shared\Converter::cmToTwip(2.00), $this->vAlignCenterCell)
                            ->addText(
                                InstanceRiskOpSuperClass::getTreatmentNameByType($riskOp['kindOfMeasure']),
                                $this->normalFont,
                                $this->leftParagraph
                            );
                        $cacheTargetedRisk = $riskOp['cacheTargetedRisk'] === '-'
                            ? $riskOp['cacheNetRisk']
                            : $riskOp['cacheTargetedRisk'];
                        $tableRiskOp->addCell(
                            PhpWord\Shared\Converter::cmToTwip(2.00),
                            $this->setBgColorCell($cacheTargetedRisk, false)
                        )->addText($cacheTargetedRisk, $this->boldFont, $this->centerParagraph);
                    }
                }
                $section->addTextBreak();
            }
        }

        return $this->getWordXmlFromWordObject($tableWord);
    }

    /**
     * Generates the Processing Activities Record's General Information data.
     *
     * @return mixed|string The WordXml data generated
     */
    private function generateTableRecordGDPR($recordId)
    {
        $record = $this->recordTable->findById((int)$recordId);

        $tableWord = new PhpWord\PhpWord();
        $tableWord->getSettings()->setUpdateFields(true);
        $section = $tableWord->addSection();
        $table = $section->addTable($this->borderTable);
        $table->addRow(400);
        $table->addCell(PhpWord\Shared\Converter::cmToTwip(4.00), $this->grayCell)
            ->addText($this->anrTranslate('Name'), $this->boldFont, $this->leftParagraph);
        $table->addCell(PhpWord\Shared\Converter::cmToTwip(14.00), $this->vAlignCenterCell)
            ->addText(_WT($record->getLabel()), $this->normalFont, $this->leftParagraph);
        $table->addRow(400);
        $table->addCell(PhpWord\Shared\Converter::cmToTwip(4.00), $this->grayCell)
            ->addText($this->anrTranslate('Creation date'), $this->boldFont, $this->leftParagraph);
        $table->addCell(PhpWord\Shared\Converter::cmToTwip(14.00), $this->vAlignCenterCell)
            ->addText(
                $record->getCreatedAt() ? strftime("%d-%m-%Y", $record->getCreatedAt()->getTimeStamp()) : '',
                $this->normalFont,
                $this->leftParagraph
            );
        $table->addRow(400);
        $table->addCell(PhpWord\Shared\Converter::cmToTwip(4.00), $this->grayCell)
            ->addText($this->anrTranslate('Update date'), $this->boldFont, $this->leftParagraph);
        $table->addCell(PhpWord\Shared\Converter::cmToTwip(14.00), $this->vAlignCenterCell)
            ->addText(
                $record->getUpdatedAt() ? strftime("%d-%m-%Y", $record->getUpdatedAt()->getTimeStamp()) : '',
                $this->normalFont,
                $this->leftParagraph
            );
        $table->addRow(400);
        $table->addCell(PhpWord\Shared\Converter::cmToTwip(4.00), $this->grayCell)
            ->addText($this->anrTranslate('Purpose(s)'), $this->boldFont, $this->leftParagraph);
        $table->addCell(PhpWord\Shared\Converter::cmToTwip(14.00), $this->vAlignCenterCell)
            ->addText(_WT($record->getPurposes()), $this->normalFont, $this->leftParagraph);
        $table->addRow(400);
        $table->addCell(PhpWord\Shared\Converter::cmToTwip(4.00), $this->grayCell)
            ->addText($this->anrTranslate('Security measures'), $this->boldFont, $this->leftParagraph);
        $table->addCell(PhpWord\Shared\Converter::cmToTwip(14.00), $this->vAlignCenterCell)
            ->addText(_WT($record->getSecMeasures()), $this->normalFont, $this->leftParagraph);

        return $this->getWordXmlFromWordObject($tableWord);
    }

    /**
     * Generates the Processing Activities Record's Joint Controllers data.
     *
     * @return mixed|string The WordXml data generated
     */
    private function generateTableRecordActors($recordId)
    {
        $record = $this->recordTable->findById((int)$recordId);
        $jointControllers = $record->getJointControllers();

        //create section
        $tableWord = new PhpWord\PhpWord();
        $section = $tableWord->addSection();
        $table = $section->addTable($this->borderTable);

        //header if array is not empty
        $table->addRow(400);
        $table->addCell(PhpWord\Shared\Converter::cmToTwip(6.00), $this->grayCell)
            ->addText($this->anrTranslate('Actor'), $this->boldFont, $this->centerParagraph);
        $table->addCell(PhpWord\Shared\Converter::cmToTwip(6.00), $this->grayCell)
            ->addText($this->anrTranslate('Name'), $this->boldFont, $this->centerParagraph);
        $table->addCell(PhpWord\Shared\Converter::cmToTwip(6.00), $this->grayCell)
            ->addText($this->anrTranslate('Contact'), $this->boldFont, $this->centerParagraph);

        $table->addRow(400);
        $table->addCell(PhpWord\Shared\Converter::cmToTwip(6.00), $this->grayCell)
            ->addText($this->anrTranslate('Controller'), $this->boldFont, $this->leftParagraph);
        $table->addCell(PhpWord\Shared\Converter::cmToTwip(6.00), $this->vAlignCenterCell)
            ->addText(
                _WT($record->getController() ? $record->getController()->getLabel() : ''),
                $this->normalFont,
                $this->leftParagraph
            );
        $table->addCell(PhpWord\Shared\Converter::cmToTwip(6.00), $this->vAlignCenterCell)
            ->addText(
                _WT($record->getController() ? $record->getController()->getContact() : ''),
                $this->normalFont,
                $this->leftParagraph
            );

        $table->addRow(400);
        $table->addCell(PhpWord\Shared\Converter::cmToTwip(6.00), $this->grayCell)
            ->addText($this->anrTranslate('Representative'), $this->boldFont, $this->leftParagraph);
        $table->addCell(PhpWord\Shared\Converter::cmToTwip(6.00), $this->vAlignCenterCell)
            ->addText(
                _WT($record->get('representative') ? $record->get('representative')->get('label') : ""),
                $this->normalFont,
                $this->leftParagraph
            );
        $table->addCell(PhpWord\Shared\Converter::cmToTwip(6.00), $this->vAlignCenterCell)
            ->addText(
                _WT($record->get('representative') ? $record->get('representative')->get('contact') : ""),
                $this->normalFont,
                $this->leftParagraph
            );

        $table->addRow(400);
        $table->addCell(PhpWord\Shared\Converter::cmToTwip(6.00), $this->grayCell)
            ->addText($this->anrTranslate('Data protection officer'), $this->boldFont, $this->leftParagraph);
        $table->addCell(PhpWord\Shared\Converter::cmToTwip(6.00), $this->vAlignCenterCell)
            ->addText(
                _WT($record->get('dpo') ? $record->get('dpo')->get('label') : ""),
                $this->normalFont,
                $this->leftParagraph
            );
        $table->addCell(PhpWord\Shared\Converter::cmToTwip(6.00), $this->vAlignCenterCell)
            ->addText(
                _WT($record->get('dpo') ? $record->get('dpo')->get('contact') : ""),
                $this->normalFont,
                $this->leftParagraph
            );

        $table->addRow(400);
        $table->addCell(PhpWord\Shared\Converter::cmToTwip(6.00), $this->grayCell)
            ->addText($this->anrTranslate('Joint controllers'), $this->boldFont, $this->leftParagraph);

        if (!empty($jointControllers)) {
            $i = 0;
            foreach ($jointControllers as $jc) {
                $table->addCell(PhpWord\Shared\Converter::cmToTwip(6.00), $this->vAlignCenterCell)
                    ->addText(_WT($jc->get('label')), $this->normalFont, $this->leftParagraph);
                $table->addCell(PhpWord\Shared\Converter::cmToTwip(6.00), $this->vAlignCenterCell)
                    ->addText(_WT($jc->get('contact')), $this->normalFont, $this->leftParagraph);
                if ($i !== \count($jointControllers) - 1) {
                    $table->addRow(400);
                    $table->addCell(PhpWord\Shared\Converter::cmToTwip(6.00), $this->grayCell);
                }
                ++$i;
            }
        } else {
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(6.00), $this->vAlignCenterCell);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(6.00), $this->vAlignCenterCell);
        }

        return $this->getWordXmlFromWordObject($tableWord);
    }

    /**
     * Generates the Processing Activities Record's Personal data.
     *
     * @return mixed|string The WordXml data generated
     */
    private function generateTableRecordPersonalData($recordId)
    {
        $recordEntity = $this->recordTable->getEntity($recordId);
        $personalData = $recordEntity->get('personalData');

        //create section
        $tableWord = new PhpWord\PhpWord();
        $section = $tableWord->addSection();

        if (!empty($personalData)) {
            $table = $section->addTable($this->borderTable);

            //header if array is not empty
            $table->addRow(400);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(3.60), $this->grayCell)
                ->addText($this->anrTranslate('Data subject'), $this->boldFont, $this->centerParagraph);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(3.60), $this->grayCell)
                ->addText($this->anrTranslate('Personal data categories'), $this->boldFont, $this->centerParagraph);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(3.60), $this->grayCell)
                ->addText($this->anrTranslate('Description'), $this->boldFont, $this->centerParagraph);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(3.60), $this->grayCell)
                ->addText($this->anrTranslate('Retention period'), $this->boldFont, $this->centerParagraph);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(3.60), $this->grayCell)
                ->addText($this->anrTranslate('Retention period description'), $this->boldFont, $this->centerParagraph);

            foreach ($personalData as $pd) {
                $table->addRow(400);
                $dataCategories = '';
                foreach ($pd->get('dataCategories') as $dc) {
                    $dataCategories .= $dc->get('label') . "\n";
                }
                $retentionPeriod = $pd->get('retentionPeriod') . ' ';
                if ($pd->get('retentionPeriodMode') === 0) {
                    $retentionPeriod .= $this->anrTranslate('day(s)');
                } else {
                    if ($pd->get('retentionPeriodMode') === 1) {
                        $retentionPeriod .= $this->anrTranslate('month(s)');
                    } else {
                        $retentionPeriod .= $this->anrTranslate('year(s)');
                    }
                }
                $table->addCell(PhpWord\Shared\Converter::cmToTwip(3.60), $this->vAlignCenterCell)
                    ->addText(_WT($pd->get('dataSubject')), $this->normalFont, $this->leftParagraph);
                $table->addCell(PhpWord\Shared\Converter::cmToTwip(3.60), $this->vAlignCenterCell)
                    ->addText(_WT($dataCategories), $this->normalFont, $this->leftParagraph);
                $table->addCell(PhpWord\Shared\Converter::cmToTwip(3.60), $this->vAlignCenterCell)
                    ->addText(_WT($pd->get('description')), $this->normalFont, $this->leftParagraph);
                $table->addCell(PhpWord\Shared\Converter::cmToTwip(3.60), $this->vAlignCenterCell)
                    ->addText(_WT($retentionPeriod), $this->normalFont, $this->leftParagraph);
                $table->addCell(PhpWord\Shared\Converter::cmToTwip(3.60), $this->vAlignCenterCell)
                    ->addText(
                        _WT($pd->get('retentionPeriodDescription')),
                        $this->normalFont,
                        $this->leftParagraph
                    );
            }
        } else {
            $section->addText(
                $this->anrTranslate('No category of personal data'),
                $this->normalFont,
                $this->leftParagraph
            );
        }

        return $this->getWordXmlFromWordObject($tableWord);
    }

    /**
     * Generates the Processing Activities Record's Recipients data
     * @return mixed|string The WordXml data generated
     */
    private function generateTableRecordRecipients($recordId)
    {
        $recordEntity = $this->recordTable->getEntity($recordId);
        $recipients = $recordEntity->get('recipients');

        //create section
        $tableWord = new PhpWord\PhpWord();
        $section = $tableWord->addSection();

        if (!empty($recipients)) {
            $table = $section->addTable($this->borderTable);

            //header if array is not empty
            $table->addRow(400);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(6.00), $this->grayCell)
                ->addText($this->anrTranslate('Recipient'), $this->boldFont, $this->centerParagraph);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(4.00), $this->grayCell)
                ->addText($this->anrTranslate('Type'), $this->boldFont, $this->centerParagraph);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(8.00), $this->grayCell)
                ->addText($this->anrTranslate('Description'), $this->boldFont, $this->centerParagraph);

            foreach ($recipients as $r) {
                $table->addRow(400);
                $table->addCell(PhpWord\Shared\Converter::cmToTwip(6.00), $this->vAlignCenterCell)
                    ->addText(_WT($r->get('label')), $this->normalFont, $this->leftParagraph);
                $table->addCell(PhpWord\Shared\Converter::cmToTwip(4.00), $this->vAlignCenterCell)
                    ->addText(
                        $r->getType() === 0 ? $this->anrTranslate('internal') : $this->anrTranslate('external'),
                        $this->normalFont,
                        $this->leftParagraph
                    );
                $table->addCell(PhpWord\Shared\Converter::cmToTwip(8.00), $this->vAlignCenterCell)
                    ->addText(_WT($r->get('description')), $this->normalFont, $this->leftParagraph);
            }
        } else {
            $section->addText($this->anrTranslate('No recipient'), $this->normalFont, $this->leftParagraph);
        }

        return $this->getWordXmlFromWordObject($tableWord);
    }

    /**
     * Generates the Processing Activities Record's International Transfers data
     * @return mixed|string The WordXml data generated
     */
    private function generateTableRecordInternationalTransfers($recordId)
    {
        $recordEntity = $this->recordTable->getEntity($recordId);
        $internationalTransfers = $recordEntity->get('internationalTransfers');

        //create section
        $tableWord = new PhpWord\PhpWord();
        $section = $tableWord->addSection();

        if (count($internationalTransfers)) {
            $table = $section->addTable($this->borderTable);

            //header if array is not empty
            $table->addRow(400);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(4.50), $this->grayCell)
                ->addText($this->anrTranslate('Organisation'), $this->boldFont, $this->centerParagraph);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(4.50), $this->grayCell)
                ->addText($this->anrTranslate('Description'), $this->boldFont, $this->centerParagraph);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(4.50), $this->grayCell)
                ->addText($this->anrTranslate('Country'), $this->boldFont, $this->centerParagraph);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(4.50), $this->grayCell)
                ->addText($this->anrTranslate('Documents'), $this->boldFont, $this->centerParagraph);

            foreach ($internationalTransfers as $it) {
                $table->addRow(400);
                $table->addCell(PhpWord\Shared\Converter::cmToTwip(4.50), $this->vAlignCenterCell)
                    ->addText(_WT($it->get('organisation')), $this->normalFont, $this->leftParagraph);
                $table->addCell(PhpWord\Shared\Converter::cmToTwip(4.50), $this->vAlignCenterCell)
                    ->addText(_WT($it->get('description')), $this->normalFont, $this->leftParagraph);
                $table->addCell(PhpWord\Shared\Converter::cmToTwip(4.50), $this->vAlignCenterCell)
                    ->addText(_WT($it->get('country')), $this->normalFont, $this->leftParagraph);
                $table->addCell(PhpWord\Shared\Converter::cmToTwip(4.50), $this->vAlignCenterCell)
                    ->addText(_WT($it->get('documents')), $this->normalFont, $this->leftParagraph);
            }
        } else {
            $section->addText(
                $this->anrTranslate('No international transfer'),
                $this->normalFont,
                $this->leftParagraph
            );
        }

        return $this->getWordXmlFromWordObject($tableWord);
    }


    /**
     * Generates the Processing Activities Record's Processors data
     * @return mixed|string The WordXml data generated
     */
    private function generateTableRecordProcessors($recordId)
    {
        $recordEntity = $this->recordTable->getEntity($recordId);
        $processors = $recordEntity->get('processors');

        $tableWord = new PhpWord\PhpWord();
        $section = $tableWord->addSection();
        if (count($processors) < 1) {
            $section->addText($this->anrTranslate('No processor'), $this->normalFont, $this->leftParagraph);
        }

        foreach ($processors as $p) {
            //create section
            $section->addText(_WT($p->get('label')), $this->boldFont);
            $table = $section->addTable($this->borderTable);

            $table->addRow(400);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(4.00), $this->grayCell)
                ->addText($this->anrTranslate('Name'), $this->boldFont, $this->leftParagraph);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(14.00), $this->vAlignCenterCell)
                ->addText(_WT($p->get('label')), $this->normalFont, $this->leftParagraph);
            $table->addRow(400);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(4.00), $this->grayCell)
                ->addText($this->anrTranslate('Contact'), $this->boldFont, $this->leftParagraph);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(14.00), $this->vAlignCenterCell)
                ->addText(_WT($p->get('contact')), $this->normalFont, $this->leftParagraph);
            $table->addRow(400);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(4.00), $this->grayCell)
                ->addText($this->anrTranslate('Activities'), $this->boldFont, $this->leftParagraph);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(14.00), $this->vAlignCenterCell)
                ->addText(_WT($p->get('activities')), $this->normalFont, $this->leftParagraph);
            $table->addRow(400);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(4.00), $this->grayCell)
                ->addText($this->anrTranslate('Security measures'), $this->boldFont, $this->leftParagraph);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(14.00), $this->vAlignCenterCell)
                ->addText(_WT($p->get('secMeasures')), $this->normalFont, $this->leftParagraph);

            $section->addTextBreak(1);
            $section->addText($this->anrTranslate('Actors'), $this->boldFont);
            $tableActor = $section->addTable($this->borderTable);

            $tableActor->addRow(400);
            $tableActor->addCell(PhpWord\Shared\Converter::cmToTwip(10.00), $this->grayCell)
                ->addText($this->anrTranslate('Actor'), $this->boldFont, $this->centerParagraph);
            $tableActor->addCell(PhpWord\Shared\Converter::cmToTwip(10.00), $this->grayCell)
                ->addText($this->anrTranslate('Name'), $this->boldFont, $this->centerParagraph);
            $tableActor->addCell(PhpWord\Shared\Converter::cmToTwip(10.00), $this->grayCell)
                ->addText($this->anrTranslate('Contact'), $this->boldFont, $this->centerParagraph);

            $tableActor->addRow(400);
            $tableActor->addCell(PhpWord\Shared\Converter::cmToTwip(10.00), $this->grayCell)
                ->addText(
                    $this->anrTranslate('Representative'),
                    $this->boldFont,
                    $this->leftParagraph
                );
            $tableActor->addCell(PhpWord\Shared\Converter::cmToTwip(10.00), $this->vAlignCenterCell)
                ->addText(
                    _WT($p->get('representative') ? $p->get('representative')->get('label') : ""),
                    $this->normalFont,
                    $this->leftParagraph
                );
            $tableActor->addCell(PhpWord\Shared\Converter::cmToTwip(10.00), $this->vAlignCenterCell)
                ->addText(
                    _WT($p->get('representative') ? $p->get('representative')->get('contact') : ""),
                    $this->normalFont,
                    $this->leftParagraph
                );

            $tableActor->addRow(400);
            $tableActor->addCell(PhpWord\Shared\Converter::cmToTwip(10.00), $this->grayCell)
                ->addText($this->anrTranslate('Data protection officer'), $this->boldFont, $this->leftParagraph);
            $tableActor->addCell(PhpWord\Shared\Converter::cmToTwip(10.00), $this->vAlignCenterCell)
                ->addText(
                    _WT($p->get('dpo') ? $p->get('dpo')->get('label') : ""),
                    $this->normalFont,
                    $this->leftParagraph
                );
            $tableActor->addCell(PhpWord\Shared\Converter::cmToTwip(10.00), $this->vAlignCenterCell)
                ->addText(
                    _WT($p->get('dpo') ? $p->get('dpo')->get('contact') : ""),
                    $this->normalFont,
                    $this->leftParagraph
                );

            $section->addTextBreak(1);
        }

        return $this->getWordXmlFromWordObject($tableWord);
    }

    /**
     * Generates all the Processing Activities Record in the anr
     * @return mixed|string The WordXml data generated
     */
    private function generateTableAllRecordsGDPR()
    {
        $recordEntities = $this->recordTable->getEntityByFields(['anr' => $this->anr->getId()]);

        $result = '';

        foreach ($recordEntities as $recordEntity) {
            $tableWord = new PhpWord\PhpWord();
            $section = $tableWord->addSection();
            $tableWord->addTitleStyle(1, $this->titleFont);
            $section->addTitle(_WT($recordEntity->get('label')), 1);
            $result .= $this->getWordXmlFromWordObject($tableWord);
            $result .= $this->generateTableRecordGDPR($recordEntity->id);
            //create section
            $tableWord = new PhpWord\PhpWord();
            $section = $tableWord->addSection();
            $tableWord->addTitleStyle(2, $this->titleFont);
            $section->addTitle($this->anrTranslate('Actors'), 2);
            $result .= $this->getWordXmlFromWordObject($tableWord);
            $result .= $this->generateTableRecordActors($recordEntity->id);
            //create section
            $tableWord = new PhpWord\PhpWord();
            $section = $tableWord->addSection();
            $tableWord->addTitleStyle(2, $this->titleFont);
            $section->addTitle($this->anrTranslate('Categories of personal data'), 2);
            $result .= $this->getWordXmlFromWordObject($tableWord);
            $result .= $this->generateTableRecordPersonalData($recordEntity->id);
            //create section
            $tableWord = new PhpWord\PhpWord();
            $section = $tableWord->addSection();
            $tableWord->addTitleStyle(2, $this->titleFont);
            $section->addTitle($this->anrTranslate('Recipients'), 2);
            $result .= $this->getWordXmlFromWordObject($tableWord);
            $result .= $this->generateTableRecordRecipients($recordEntity->id);
            //create section
            $tableWord = new PhpWord\PhpWord();
            $section = $tableWord->addSection();
            $tableWord->addTitleStyle(2, $this->titleFont);
            $section->addTitle($this->anrTranslate('International transfers'), 2);
            $result .= $this->getWordXmlFromWordObject($tableWord);
            $result .= $this->generateTableRecordInternationalTransfers($recordEntity->id);
            //create section
            $tableWord = new PhpWord\PhpWord();
            $section = $tableWord->addSection();
            $tableWord->addTitleStyle(2, $this->titleFont);
            $section->addTitle($this->anrTranslate('Processors'), 2);
            $result .= $this->getWordXmlFromWordObject($tableWord);
            $result .= $this->generateTableRecordProcessors($recordEntity->id);
        }

        return $result;
    }

    /**
     * Generate the impacts appreciation table data
     * @return mixed|string The WordXml table data
     */
    private function generateImpactsAppreciation()
    {
        $instances = $this->instanceTable->findInstancesByAnrWithEvaluationAndNotInheritedOrderBy(
            $this->anr,
            ['i.position' => 'ASC']
        );
        $impacts = ['c', 'i', 'd'];
        $instanceCriteria = Entity\Instance::getAvailableScalesCriteria();

        $tableWord = new PhpWord\PhpWord();
        $section = $tableWord->addSection();
        $table = $section->addTable($this->borderTable);

        //header
        if (!empty($instances)) {
            $table->addRow(400, $this->tblHeader);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(9.00), $this->setColSpanCell(3, 'DFDFDF'))
                ->addText($this->anrTranslate('Impact'), $this->boldFont, $this->centerParagraph);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(9.00), $this->setColSpanCell(3, 'DFDFDF'))
                ->addText($this->anrTranslate('Consequences'), $this->boldFont, $this->centerParagraph);
        }

        $globalObjectsUuids = [];
        foreach ($instances as $instance) {
            /* Check if the global object is already added. */
            if (in_array($instance->getObject()->getUuid(), $globalObjectsUuids, true)) {
                continue;
            }

            $instanceConsequences = $this->anrInstanceConsequenceService->getConsequencesData($instance, true);

            //delete scale type C,I and D
            // set the correct order in the deliverable. not perfect but work
            $impactsConsequences = [];
            foreach ($instanceConsequences as $keyConsequence => $instanceConsequence) {
                if ($instanceConsequence['scaleImpactType'] < 4) {
                    unset($instanceConsequences[$keyConsequence]);
                }
                $impactsConsequences[$instanceConsequence['scaleImpactType'] - 1] = $instanceConsequence;
            }
            //reinitialization keys
            $instanceConsequences = array_values($instanceConsequences);
            $headerImpact = false;
            foreach ($impacts as $keyImpact => $impact) {
                $headerConsequence = false;
                foreach ($instanceConsequences as $instanceConsequence) {
                    if ($instanceConsequence[$impact . '_risk'] >= 0) {
                        if (!$headerImpact && !$headerConsequence) {
                            $table->addRow(400);
                            $table->addCell(PhpWord\Shared\Converter::cmToTwip(16), $this->setColSpanCell(6, 'DBE5F1'))
                                ->addText(
                                    _WT($instance->getName($this->currentLangAnrIndex)),
                                    $this->boldFont,
                                    $this->leftParagraph
                                );
                            $headerImpact = true;
                            if ($instance->getObject()->isScopeGlobal()) {
                                $globalObjectsUuids[] = $instance->getObject()->getUuid();
                            }
                        }
                        $table->addRow(400);
                        if (!$headerConsequence) {
                            $comment = $impactsConsequences[$keyImpact]['comments'][
                                $instance->{'get' . $instanceCriteria[$impact]}() !== -1
                                ? $instance->{'get' . $instanceCriteria[$impact]}()
                                : 0
                            ] ?? '';
                            $translatedImpact = ucfirst($impact);
                            if ($impact === 'd') {
                                $translatedImpact = ucfirst($this->anrTranslate('A'));
                            }
                            $table->addCell(PhpWord\Shared\Converter::cmToTwip(1.00), $this->restartAndCenterCell)
                                ->addText($translatedImpact, $this->boldFont, $this->centerParagraph);
                            $table->addCell(PhpWord\Shared\Converter::cmToTwip(1.00), $this->restartAndCenterCell)
                                ->addText(
                                    $instance->{'get' . $instanceCriteria[$impact]}(),
                                    $this->boldFont,
                                    $this->centerParagraph
                                );
                            $table->addCell(PhpWord\Shared\Converter::cmToTwip(5.00), $this->restartAndCenterCell)
                                ->addText(_WT($comment), $this->normalFont, $this->leftParagraph);
                        } else {
                            $table->addCell(PhpWord\Shared\Converter::cmToTwip(1.00), $this->continueCell);
                            $table->addCell(PhpWord\Shared\Converter::cmToTwip(1.00), $this->continueCell);
                            $table->addCell(PhpWord\Shared\Converter::cmToTwip(5.00), $this->continueCell);
                        }
                        $comment = $instanceConsequence['comments'][
                            $instanceConsequence[$impact . '_risk'] !== -1 ? $instanceConsequence[$impact . '_risk'] : 0
                        ] ?? '';
                        $table->addCell(PhpWord\Shared\Converter::cmToTwip(1.00), $this->vAlignCenterCell)
                            ->addText(
                                _WT($instanceConsequence['scaleImpactTypeDescription' . $this->currentLangAnrIndex]),
                                $this->boldFont,
                                $this->centerParagraph
                            );
                        $table->addCell(PhpWord\Shared\Converter::cmToTwip(1.00), $this->vAlignCenterCell)
                            ->addText($instanceConsequence[$impact . '_risk'], $this->boldFont, $this->centerParagraph);
                        $table->addCell(PhpWord\Shared\Converter::cmToTwip(7.00), $this->vAlignCenterCell)
                            ->addText(_WT($comment), $this->normalFont, $this->leftParagraph);

                        $headerConsequence = true;
                    }
                }
            }
        }

        return $table;
    }

    /**
     * Generate the threats table data.
     *
     * @param bool $fullGen Whether to generate the full table (all but normal) or just the normal threats.
     *
     * @return mixed|string The WordXml generated data.
     */
    private function generateThreatsTable($fullGen = false)
    {
        /** @var Entity\Threat[] $threats */
        $threats = $this->threatTable->findByAnr($this->anr);
        $nbThreats = 0;
        foreach ($threats as $threat) {
            if ($fullGen || $threat->getTrend() !== 1) {
                $nbThreats++;
            }
        }

        $tableWord = new PhpWord\PhpWord();
        $section = $tableWord->addSection();
        $table = $section->addTable($this->borderTable);

        if ($nbThreats > 0) {
            $table->addRow(400, $this->tblHeader);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(7.60), $this->grayCell)
                ->addText($this->anrTranslate('Threat'), $this->boldFont, $this->centerParagraph);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(1.50), $this->grayCell)
                ->addText($this->anrTranslate('CIA'), $this->boldFont, $this->centerParagraph);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(1.70), $this->grayCell)
                ->addText($this->anrTranslate('Tend.'), $this->boldFont, $this->centerParagraph);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(1.60), $this->grayCell)
                ->addText($this->anrTranslate('Prob.'), $this->boldFont, $this->centerParagraph);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(6.60), $this->grayCell)
                ->addText($this->anrTranslate('Comment'), $this->boldFont, $this->centerParagraph);
        }

        foreach ($threats as $threat) {
            if ($fullGen || $threat->getTrend() !== 1) {
                $table->addRow(400);
                $table->addCell(PhpWord\Shared\Converter::cmToTwip(5.85), $this->vAlignCenterCell)
                    ->addText(
                        _WT($threat->getLabel($this->currentLangAnrIndex)),
                        $this->normalFont,
                        $this->leftParagraph
                    );

                // CID
                $cid = '';
                if ($threat->getConfidentiality()) {
                    $cid .= 'C';
                }
                if ($threat->getIntegrity()) {
                    $cid .= 'I';
                }
                if ($threat->getAvailability()) {
                    $cid .= $this->anrTranslate('A');
                }
                $table->addCell(PhpWord\Shared\Converter::cmToTwip(1.50), $this->vAlignCenterCell)
                    ->addText($cid, $this->normalFont, $this->centerParagraph);

                // Trend
                $trend = match ($threat->getTrend()) {
                    0 => '-',
                    1 => 'n',
                    2 => '+',
                    3 => '++',
                    default => '',
                };
                $table->addCell(PhpWord\Shared\Converter::cmToTwip(1.70), $this->vAlignCenterCell)
                    ->addText($trend, $this->normalFont, $this->centerParagraph);

                // Pre-Q
                $table->addCell(PhpWord\Shared\Converter::cmToTwip(1.60), $this->vAlignCenterCell)
                    ->addText(
                        $threat->getQualification() >= 0 ? $threat->getQualification() : '',
                        $this->normalFont,
                        $this->centerParagraph
                    );
                $table->addCell(PhpWord\Shared\Converter::cmToTwip(6.60), $this->vAlignCenterCell)
                    ->addText(_WT($threat->getComment()), $this->normalFont, $this->leftParagraph);
            }
        }

        return $table;
    }

    /**
     * Generate the owner table data
     * @return mixed|string The WordXml generated data
     */
    private function generateOwnersTable()
    {
        $allOwners = $this->instanceRiskOwnerTable->findByAnr($this->anr);
        $globalObjectsUuids = [];

        foreach ($allOwners as $owner) {
            if (!empty($owner->getInstanceRisks())) {
                foreach ($owner->getInstanceRisks() as $ir) {
                    /* Check if the global object is already added. */
                    $uniqueKey = $ir->getInstance()->getObject()->getUuid()
                        . $ir->getThreat()->getUuid()
                        . $ir->getVulnerability()->getUuid();

                    if (in_array($uniqueKey, $globalObjectsUuids, true)) {
                        continue;
                    }

                    if ($ir->getInstance()->getObject()->isScopeGlobal()) {
                        $asset = $ir->getInstance()->getName($this->currentLangAnrIndex) . ' ('
                            . $this->anrTranslate('Global') . ')';
                        $globalObjectsUuids[] = $uniqueKey;
                    } else {
                        $asset = $ir->getInstance()->getHierarchyString();
                    }
                    $risksByOwner[$owner->getName()][] = [
                        'asset' => $asset,
                        'threat' => $ir->getThreat()->getLabel($this->currentLangAnrIndex),
                        'vulnerability' => $ir->getVulnerability()->getLabel($this->currentLangAnrIndex),
                    ];
                }
            }
            if (!empty($owner->getOperationalInstanceRisks())) {
                foreach ($owner->getOperationalInstanceRisks() as $oir) {
                    $risksByOwner[$owner->getName()][] = [
                        'asset' => $oir->getInstance()->getHierarchyString(),
                        'risk' => $oir->getRiskCacheLabel($this->currentLangAnrIndex),
                    ];
                }
            }
        }

        $tableWord = new PhpWord\PhpWord();
        $section = $tableWord->addSection();
        $table = $section->addTable($this->borderTable);

        if (!empty($risksByOwner)) {
            $table->addRow(400, $this->tblHeader);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(2.00), $this->grayCell)
                ->addText($this->anrTranslate('Owner'), $this->boldFont, $this->centerParagraph);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(6.00), $this->grayCell)
                ->addText($this->anrTranslate('Asset'), $this->boldFont, $this->centerParagraph);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(10.00), $this->setColSpanCell(2, 'DFDFDF'))
                ->addText($this->anrTranslate('Risk'), $this->boldFont, $this->centerParagraph);
            foreach ($risksByOwner as $owner => $risks) {
                $isOwnerHeader = true;
                foreach ($risks as $risk) {
                    $table->addRow(400);
                    if ($isOwnerHeader) {
                        $table->addCell(PhpWord\Shared\Converter::cmToTwip(2.00), $this->restartAndCenterCell)
                            ->addText(_WT($owner), $this->boldFont, $this->leftParagraph);
                    } else {
                        $table->addCell(PhpWord\Shared\Converter::cmToTwip(2.00), $this->continueCell);
                    }
                    $table->addCell(PhpWord\Shared\Converter::cmToTwip(6.00), $this->vAlignCenterCell)
                        ->addText(_WT($risk['asset']), $this->normalFont, $this->leftParagraph);
                    if (isset($risk['threat'])) {
                        $table->addCell(PhpWord\Shared\Converter::cmToTwip(3.00), $this->vAlignCenterCell)
                            ->addText(_WT($risk['threat']), $this->normalFont, $this->leftParagraph);
                        $table->addCell(PhpWord\Shared\Converter::cmToTwip(7.00), $this->vAlignCenterCell)
                            ->addText(_WT($risk['vulnerability']), $this->normalFont, $this->leftParagraph);
                    } else {
                        $table->addCell(PhpWord\Shared\Converter::cmToTwip(10.00), $this->setColSpanCell(2))
                            ->addText(_WT($risk['risk']), $this->normalFont, $this->leftParagraph);
                    }
                    $isOwnerHeader = false;
                }
            }
        }

        return $table;
    }

    /**
     * Generate the asset context table data.
     *
     * @return mixed|string The WordXml generated data
     */
    private function generateAssetContextTable()
    {
        /** @var Entity\AnrInstanceMetadataField[] $anrMetadataFields */
        $anrMetadataFields = $this->anrInstanceMetadataFieldTable->findByAnr($this->anr);
        $metadataFieldsHeaders = [];
        foreach ($anrMetadataFields as $anrMetadataField) {
            $metadataFieldsHeaders[] = $anrMetadataField->getLabel();
        }
        if (empty($metadataFieldsHeaders)) {
            return null;
        }

        $sizeColumn = 13 / count($metadataFieldsHeaders);

        $tableWord = new PhpWord\PhpWord();
        $section = $tableWord->addSection();
        $tableWord->addTitleStyle(3, $this->titleFont);

        $assetUuids = [];
        /** @var Entity\Instance[] $instances */
        $instances = $this->instanceTable->findByAnr($this->anr);
        foreach ($instances as $instance) {
            $assetUuid = $instance->getAsset()->getUuid();
            if (in_array($assetUuid, $assetUuids, true)) {
                continue;
            }
            $assetUuids[] = $assetUuid;
            $typeAsset = $instance->getAsset()->isPrimary() ? 'PrimaryAssets' : 'SecondaryAssets';
            $assetLabel = $instance->getName($this->currentLangAnrIndex);
            if ($instance->getObject()->isScopeGlobal()) {
                $assetLabel .= ' (' . $this->anrTranslate('Global') . ')';
            }

            if (!isset(${'table' . $typeAsset})) {
                $section->addTitle(
                    $this->anrTranslate($instance->getAsset()->isPrimary() ? 'Primary assets' : 'Secondary assets'),
                    3
                );
                ${'table' . $typeAsset} = $section->addTable($this->borderTable);
                ${'table' . $typeAsset}->addRow(400, $this->tblHeader);
                ${'table' . $typeAsset}->addCell(PhpWord\Shared\Converter::cmToTwip(4.00), $this->grayCell)
                    ->addText($this->anrTranslate('Asset'), $this->boldFont, $this->centerParagraph);
                foreach ($metadataFieldsHeaders as $headerMetadata) {
                    ${'table' . $typeAsset}->addCell(PhpWord\Shared\Converter::cmToTwip($sizeColumn), $this->grayCell)
                        ->addText(_WT($headerMetadata), $this->boldFont, $this->centerParagraph);
                }
            }

            ${'table' . $typeAsset}->addRow(400);
            ${'table' . $typeAsset}->addCell(PhpWord\Shared\Converter::cmToTwip(4.00), $this->vAlignCenterCell)
                ->addText(_WT($assetLabel), $this->normalFont, $this->leftParagraph);

            $instanceMetadata = $instance->getInstanceMetadata();
            foreach ($anrMetadataFields as $anrMetadataField) {
                if (!$instanceMetadata->isEmpty()) {
                    $metadataFiltered = array_filter(
                        $instanceMetadata->toArray(),
                        static function ($im) use ($anrMetadataField) {
                            /** @var Entity\InstanceMetadata $im */
                            return $anrMetadataField->getId() === $im->getAnrInstanceMetadataField()->getId();
                        }
                    );
                }
                $translationComment = null;

                if (!empty($metadataFiltered)) {
                    $translationComment = $translations[reset($metadataFiltered)->getCommentTranslationKey()] ?? null;
                }

                ${'table' . $typeAsset}->addCell(
                    PhpWord\Shared\Converter::cmToTwip($sizeColumn),
                    $this->vAlignCenterCell
                )->addText(
                    $translationComment !== null ? _WT($translationComment->getValue()) : '',
                    $this->normalFont,
                    $this->leftParagraph
                );
            }
        }

        return $this->getWordXmlFromWordObject($tableWord);
    }

    /**
     * Generates WordXml data from HTML.
     *
     * @param string $input HTML input
     *
     * @return string WordXml data
     */
    private function generateWordXmlFromHtml($input)
    {
        // Process trix caveats
        $input = html_entity_decode($input);
        $input = str_replace(
            ['&lt;', '&gt;', '&amp;', '<br>'],
            ['[escape_lt]', '[escape_gt]', '[escape_amp]', '<!--block-->'],
            $input
        );

        while (str_contains($input, '<ul>')) {
            if (preg_match_all("'<ul>(.*?)</ul>'", $input, $groups)) {
                foreach ($groups as $group) {
                    $value1 = preg_replace(
                        ["'<li><!--block-->'", "'</li>'"],
                        ['<!--block-->&nbsp;&nbsp;&bull;&nbsp;', '<!--block-->'],
                        $group[0]
                    );

                    $input = preg_replace("'<ul>(.*?)</ul>'", "$value1", $input, 1);
                }
            }
        }

        while (str_contains($input, '<ol>')) {
            if (preg_match_all("'<ol>(.*?)</ol>'", $input, $groups)) {
                foreach ($groups as $group) {
                    $index = 0;
                    while (strpos($group[0], '<li>') !== false) {
                        $index += 1;
                        $group[0] = preg_replace(
                            ["'<li><!--block-->'", "'</li>'"],
                            ["<!--block-->&nbsp;&nbsp;[$index]&nbsp;", '<!--block-->'],
                            $group[0],
                            1
                        );
                    }
                    $input = preg_replace("'<ol>(.*?)</ol>'", "$group[0]", $input, 1);
                }
            }
        }

        // Turn it into word data
        $phpWord = new PhpWord\PhpWord();
        $section = $phpWord->addSection();
        PhpWord\Shared\Html::addHtml($section, $input);

        return $this->getWordXmlFromWordObject($phpWord);
    }

    /**
     * Generates the instances tree
     *
     * @param array $elements instances risks array
     * @param int $parentId id of parent_Root
     *
     * @return array
     */
    private function buildTree($elements, $parentId)
    {
        $branch = [];
        foreach ($elements as $element => $value) {
            if ($value['parent'] === $parentId) {
                $children = $this->buildTree($elements, $element);
                if ($children) {
                    usort($children, static function ($a, $b) {
                        return $a['position'] <=> $b['position'];
                    });
                    $value['children'] = $children;
                }
                $branch[] = $value;
            } elseif (!isset($value['parent']) && $parentId === $element) {
                $branch[] = $value;
            }
        }
        usort($branch, static function ($a, $b) {
            return $a['position'] <=> $b['position'];
        });

        return $branch;
    }

    /**
     * Generates a single-level array from multilevel array
     *
     * @param array $multiLevelArray
     *
     * @return array
     */
    private function singleLevelArray($multiLevelArray)
    {
        $singleLevelArray = [];
        foreach ($multiLevelArray as $a) {
            if (isset($a['children'])) {
                $singleLevelArray[] = $a;
                $children_array = $this->singleLevelArray($a['children']);
                foreach ($children_array as $children) {
                    $singleLevelArray[] = $children;
                }
            } else {
                $singleLevelArray[] = $a;
            }
        }

        return $singleLevelArray;
    }

    /**
     * Retrieves the WordXml data from a generated PhpWord Object
     *
     * @return string The WordXml data
     */
    private function getWordXmlFromWordObject(PhpWord\PhpWord $phpWord)
    {
        $part = new PhpWord\Writer\Word2007\Part\Document();
        $part->setParentWriter(new PhpWord\Writer\Word2007($phpWord));
        $docXml = $part->write();
        $matches = [];
        $regex = '/<w:body>(.*)<w:sectPr>/is';

        if (preg_match($regex, $docXml, $matches) === 1) {
            $matches[1] = str_replace(
                ['[escape_lt]', '[escape_gt]', '[escape_amp]'],
                ['&lt;', '&gt;', '&amp;'],
                $matches[1]
            );

            return $matches[1];
        }
    }

    private function getObjectInstancePath(Entity\RecommendationRisk $recommendationRisk): string
    {
        if ($recommendationRisk->hasGlobalObjectRelation()) {
            return $recommendationRisk->getInstance()->getName($recommendationRisk->getAnr()->getLanguage())
                . ' (' . $this->anrTranslate('Global') . ')';
        }

        return $recommendationRisk->getInstance()->getHierarchyString();
    }

    private function getInstancePathFromHierarchy(array $instanceHierarchyArray): string
    {
        return implode(' > ', array_column($instanceHierarchyArray, 'name' . $this->currentLangAnrIndex));
    }
}

function _WT(string $input)
{
    $input = htmlspecialchars(trim($input), ENT_COMPAT);

    return str_replace("\n", '</w:t><w:br/><w:t>', $input);
}
