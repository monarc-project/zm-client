<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Model\Entity\OperationalRiskScaleSuperClass;
use Monarc\Core\Service\AbstractService;
use Monarc\Core\Service\DeliveriesModelsService;
use Monarc\Core\Service\QuestionChoiceService;
use Monarc\Core\Service\QuestionService;
use Monarc\Core\Service\ConfigService;
use Monarc\Core\Service\TranslateService;
use Monarc\FrontOffice\Model\Entity\Anr;
use Monarc\FrontOffice\Model\Entity\Instance;
use Monarc\FrontOffice\Model\Entity\MonarcObject;
use Monarc\FrontOffice\Model\Entity\RecommandationRisk;
use Monarc\FrontOffice\Table\AnrTable;
use Monarc\FrontOffice\Model\Table\DeliveryTable;
use Monarc\FrontOffice\Model\Table\InstanceRiskOpTable;
use Monarc\FrontOffice\Model\Table\InstanceRiskTable;
use Monarc\FrontOffice\Model\Table\InstanceTable;
use Monarc\FrontOffice\Model\Table\RecommandationRiskTable;
use Monarc\FrontOffice\Model\Table\RecommendationHistoricTable;
use Monarc\FrontOffice\Table\AnrInstanceMetadataFieldTable;
use Monarc\FrontOffice\Table\SoaScaleCommentTable;
use Monarc\FrontOffice\Table\ClientTable;
use Monarc\FrontOffice\Table\InstanceRiskOwnerTable;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Converter;
use PhpOffice\PhpWord\TemplateProcessor;
use PhpOffice\PhpWord\Writer\Word2007;
use PhpOffice\PhpWord\Writer\Word2007\Part\Document;
use PhpOffice\PhpWord\Shared\Html;

/**
 * This class is the service that handles the generation of the deliverable Word documents throughout the steps of the
 * risk analysis.
 * @package Monarc\FrontOffice\Service
 */
class DeliverableGenerationService extends AbstractService
{
    /** @var DeliveryTable */
    protected $table;
    protected $entity;
    protected $dependencies = ['anr'];

    /** @var DeliveriesModelsService */
    protected $deliveryModelService;
    /** @var ClientTable */
    protected $clientTable;
    /** @var AnrTable */
    protected $anrTable;
    /** @var AnrScaleService */
    protected $scaleService;
    /** @var AnrScaleTypeService */
    protected $scaleTypeService;
    /** @var AnrScaleCommentService */
    protected $scaleCommentService;
    /** @var OperationalRiskScaleService */
    protected $operationalRiskScaleService;
    /** @var QuestionService */
    protected $questionService;
    /** @var QuestionChoiceService */
    protected $questionChoiceService;
    /** @var AnrInterviewService */
    protected $interviewService;
    /** @var AnrThreatService */
    protected $threatService;
    /** @var AnrCartoRiskService */
    protected $cartoRiskService;
    /** @var ConfigService */
    protected $configService;

    protected AnrInstanceConsequenceService $anrInstanceConsequenceService;

    protected InstanceTable $instanceTable;

    /** @var InstanceRiskTable */
    protected $instanceRiskTable;
    /** @var InstanceRiskOpTable */
    protected $instanceRiskOpTable;
    /** @var SoaService */
    protected $soaService;
    /** @var SoaScaleCommentTable */
    protected $soaScaleCommentTable;
    /** @var AnrMeasureService */
    protected $measureService;
    /** @var AnrInstanceRiskOpService */
    protected $anrInstanceRiskOpService;
    /** @var AnrInstanceRiskService */
    protected $anrInstanceRiskService;
    /** @var AnrRecordService */
    protected $recordService;
    /** @var TranslateService */
    protected $translateService;
    /** @var InstanceRiskOwnerTable */
    protected $instanceRiskOwnerTable;
    /** @var RecommandationRiskTable */
    protected $recommendationRiskTable;
    /** @var RecommendationHistoricTable */
    protected $recommendationHistoricTable;
    /** @var AnrInstanceMetadataFieldTable */
    protected $metadatasOnInstancesTable;
    /** @var TranslationTable */
    protected $translationTable;

    protected $currentLangAnrIndex = 1;

    /** @var Anr */
    protected $anr;

    protected $noBorderTable;
    protected $borderTable;
    protected $whiteBigBorderTable;
    protected $tblHeader;

    protected $normalFont;
    protected $boldFont;
    protected $whiteFont;
    protected $redFont;
    protected $titleFont;

    protected $centerParagraph;
    protected $leftParagraph;
    protected $verticalCenterParagraph;

    protected $grayCell;
    protected $blackCell;
    protected $customizableCell;
    protected $vAlignCenterCell;
    protected $continueCell;
    protected $colSpanCell;
    protected $rotate90TextCell;
    protected $restartAndGrayCell;
    protected $continueAndGrayCell;
    protected $restartAndBlackCell;
    protected $continueAndBlackCell;
    protected $restartAndCenterCell;
    protected $restartAndTopCell;

    protected $barChart;

    /**
     * Language field setter
     * @param string $lang
     */
    public function setLanguage($lang)
    {
        $this->language = $lang;
    }

    /**
     * Translates the provided input text into the current ANR language
     * @param string $text The text to translate
     * @return string THe translated text, or $text if no translation was found
     */
    public function anrTranslate($text)
    {
        return $this->get('translateService')->translate($text, $this->currentLangAnrIndex);
    }

    /**
     * Returns the list of delivery models.
     * @return array An array of delivery models
     * @see DeliveriesModelsService::getList()
     */
    public function getDeliveryModels()
    {
        return $this->deliveryModelService->getList(1, 0, null, null, null);
    }

    /**
     * Retrieve the previous delivery for the specified type of document, or all types if none is specified.
     * @param int $anrId The ANR ID
     * @param null|int $typeDoc The type of document, or null to retrieve all
     * @return array The previous deliveries
     */
    public function getLastDeliveries($anrId, $typeDoc = null)
    {
        /** @var DeliveryTable $table */
        $table = $this->get('table');

        // If typedoc is specified, retrieve only the last delivery of typedoc. Else, retrieve last delivery for each
        // type of document.
        if (!empty($typeDoc)) {
            $deliveries = $table->getEntityByFields(['anr' => $anrId, 'typedoc' => $typeDoc], ['createdAt' => 'DESC']);
            $lastDelivery = null;
            foreach ($deliveries as $delivery) {
                $lastDelivery = $delivery->getJsonArray();
                break;
            }

            return $lastDelivery;
        } else {
            $deliveries = $table->getEntityByFields(['anr' => $anrId], ['createdAt' => 'DESC']);
            $lastDelivery = [];
            foreach ($deliveries as $delivery) {
                if (empty($lastDelivery[$delivery->get('typedoc')])) {
                    $lastDelivery[$delivery->get('typedoc')] = $delivery->getJsonArray();
                }
                if (count($lastDelivery) == 3) {
                    break;
                }
            }

            return array_values($lastDelivery);
        }
    }

    /**
     * Generates the deliverable Word file
     * @param int $anrId The ANR ID
     * @param int $typeDoc The type of document model
     * @param array $values The values to fill in the document
     * @param array $data The user-provided data when generating the deliverable
     * @return string The output file path
     * @throws \Monarc\Core\Exception\Exception If the model or ANR are not found.
     */
    public function generateDeliverableWithValues($anrId, $typeDoc, $values, $data)
    {
        $this->anr = $this->anrTable->findById($anrId);
        $this->currentLangAnrIndex = $this->anr->getLanguage();

        $model = current($this->deliveryModelService->get("table")->getEntityByFields(['id' => $data['template']]));
        if (!$model) {
            throw new \Monarc\Core\Exception\Exception("Model `id` not found");
        }

        $delivery = $this->get('entity');

        $data['respCustomer'] = $data['consultants'];
        $data['respSmile'] = $data['managers'];
        $data['name'] = $data['docname'];

        $values['txt']['SUMMARY_EVAL_RISK'] = $this->generateWordXmlFromHtml(_WT($values['txt']['SUMMARY_EVAL_RISK']));

        unset($data['id']);
        $delivery->exchangeArray($data);

        $dependencies = (property_exists($this, 'dependencies')) ? $this->dependencies : [];
        $this->setDependencies($delivery, $dependencies);

        /** @var DeliveryTable $table */
        $table = $this->get('table');
        $table->save($delivery);

        //find the right model
        $pathModel = getenv('APP_CONF_DIR') ? getenv('APP_CONF_DIR') : '';
        $pathLang = '';

        switch ($this->currentLangAnrIndex) {
            case 1:
                $pathLang = $model->path1;
                break;
            case 2:
                $pathLang = $model->path2;
                break;
            case 3:
                $pathLang = $model->path3;
                break;
            case 4:
                $pathLang = $model->path4;
                break;
            default:
                break;
        }
        $pathModel .= $pathLang;
        if (!file_exists($pathModel)) {
            // if template not available in the language of the ANR, use the
            // default template of the category
            $pathModel = getenv('APP_CONF_DIR') ? getenv('APP_CONF_DIR') : '';
            $model = current($this->deliveryModelService->get("table")->getEntityByFields(['category' => $typeDoc,
                'path2' => ['op' => 'IS NOT', 'value' => null]]));
            $pathModel .= $model->path2;
            // throw new \Monarc\Core\Exception\Exception("Model not found for the language");
        }

        if (!file_exists($pathModel)) {
            throw new \Monarc\Core\Exception\Exception("Model not found " . $pathModel);
        }

        $referential = $data['referential'] ?? null;

        $risksByControl = $data['risksByControl'] ?? false;

        $record = $data['record'] ?? null;

        $values = array_merge_recursive($values, $this->buildValues($typeDoc, $referential, $record, $risksByControl));

        return $this->generateDeliverableWithValuesAndModel($pathModel, $values);
    }

    /**
     * Method called by generateDeliverableWithValues to generate the model from its path and values.
     * @see #generateDeliverableWithValues
     * @param string $modelPath The file path to the DOCX model to use
     * @param array $values The values to fill in the document
     * @return string The path to the generated document
     * @throws \Monarc\Core\Exception\Exception If the model is not found
     */
    protected function generateDeliverableWithValuesAndModel($modelPath, $values)
    {
        //verify template exist
        if (!file_exists($modelPath)) {
            throw new \Monarc\Core\Exception\Exception("Model path not found: " . $modelPath);
        }

        //create word
        $word = new TemplateProcessor($modelPath);

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

        $datapath = './data/';
        $appconfdir = getenv('APP_CONF_DIR') ? getenv('APP_CONF_DIR') : '';
        if (!empty($appconfdir)) {
            $datapath = $appconfdir . '/data/';
        }
        $pathTmp = $datapath . uniqid("", true) . "_" . microtime(true) . ".docx";
        $word->saveAs($pathTmp);

        // Test export to PDF with DomPDF
        // $pathTmp1 = $datapath . uniqid("", true) . "_" . microtime(true) . ".pdf";
        // \PhpOffice\PhpWord\Settings::setPdfRendererPath('vendor/dompdf/dompdf');
        // \PhpOffice\PhpWord\Settings::setPdfRendererName('DomPDF');
        // $phpWord = \PhpOffice\PhpWord\IOFactory::load($pathTmp);
        // //Save it
        // $xmlWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'PDF');
        // $xmlWriter->save($pathTmp1);

        return $pathTmp;
    }

    /**
     * Returns a human-readable string for the provided model type
     * @param int $modelCategory The model type value
     * @return string The model type description
     */
    protected function getModelType($modelCategory)
    {
        switch ($modelCategory) {
            case 1:
                return 'Validation du contexte';
            case 2:
                return 'Validation du modèle';
            case 3:
                return 'Rapport final';
            case 4:
                return 'Plan implementation des recommendations';
            case 5:
                return 'Statement of applicability';
            default:
                return 'N/A';
        }
    }

    /**
     * Builds the values to fill in the word document
     * @param int $modelCategory The model type
     * @return array The values for the Word document as a key-value array
     */
    protected function buildValues($modelCategory, $referential = null, $record = null, $risksByControl = false)
    {
        $this->setStyles();

        switch ($modelCategory) {
            case 1:
                return $this->buildContextValidationValues();
            case 2:
                return $this->buildContextModelingValues();
            case 3:
                return $this->buildRiskAssessmentValues();
            case 4:
                return $this->buildImplementationPlanValues();
            case 5:
                return $this->buildStatementOfAppplicabilityValues($referential, $risksByControl);
            case 6:
                return $this->buildRecordOfProcessingActivitiesValues($record);
            case 7:
                return $this->buildAllRecordsValues();
            default:
                return [];
        }
    }

    /**
     * Set table styles
     */
    protected function setStyles()
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
            ['vMerge' => 'restart','textDirection' => 'btLr']
        );
        $this->restartAndGrayCell = array_merge($this->grayCell, ['vMerge' => 'restart']);
        $this->continueAndGrayCell = array_merge($this->continueCell, $this->grayCell);
        $this->restartAndBlackCell = array_merge($this->blackCell, ['vMerge' => 'restart']);
        $this->continueAndBlackCell = array_merge($this->continueCell, $this->blackCell);
        $this->restartAndCenterCell = array_merge($this->vAlignCenterCell, ['vMerge' => 'restart']);
        $this->restartAndTopCell = ['vMerge' => 'restart', 'valign' => 'top'];

        //Chart styles
        $this->barChart = [
            'width' => Converter::cmToEmu(17),
            'height' => Converter::cmToEmu(9.5),
            'dataLabelOptions' => ['showCatName' => false],
            'colors' => ['D6F107','FFBC1C','FD661F'],
            'showAxisLabels' => true,
            'showGridY' => true,
        ];
    }

    /**
     * Set Span and Color Cell
     * @param int $nCol number of columns
     * @param string $color HEX color
     * @return array $this->colSpanCell
     */
    protected function setColSpanCell($nCol, $color = null)
    {
        $this->colSpanCell['gridSpan'] = $nCol;
        $this->colSpanCell['bgcolor'] = $color;
        return $this->colSpanCell;
    }

    /**
     * Set bgColor by thresholds value
     * @param int $nCol number of columns
     * @param string $color HEX color
     * @return array $this->colSpanCell
     */
    protected function setBgColorCell($value, $infoRisk = true)
    {

        if ($infoRisk) {
            $thresholds = [
                'low' => $this->anr->seuil1,
                'high' => $this->anr->seuil2,
            ];
        } else {
            $thresholds = [
                'low' => $this->anr->seuilRolf1,
                'high' => $this->anr->seuilRolf2,
            ];
        }

        if ($value === null) {
            $this->customizableCell['BgColor'] = 'E7E6E6';
            return $this->customizableCell;
        }

        if ($value === '-') {
            $this->customizableCell['BgColor'] = '';
        } elseif ($value <= $thresholds['low']) {
            $this->customizableCell['BgColor'] = 'D6F107';
        } elseif ($value <= $thresholds['high']) {
            $this->customizableCell['BgColor'] = 'FFBC1C';
        } else {
            $this->customizableCell['BgColor'] = 'FD661F';
        }

        return $this->customizableCell;
    }

    /**
     * Build values for Step 1 deliverable (context validation)
     * @return array The key-value array
     */
    protected function buildContextValidationValues()
    {
        $impactsScale = current(current(
            $this->scaleService->getList(1, 0, null, null, ['anr' => $this->anr->getId(), 'type' => 1])
        ));
        $impactsTypes = $this->scaleTypeService->getList(1, 0, null, null, ['anr' => $this->anr->getId()]);
        $impactsComments = $this->scaleCommentService->getList(
            1,
            0,
            null,
            null,
            ['anr' => $this->anr->getId(), 'scale' => $impactsScale['id']]
        );
        $threatsScale = current(current($this->scaleService->getList(
            1,
            0,
            null,
            null,
            ['anr' => $this->anr->getId(), 'type' => 2]
        )));
        $threatsComments = $this->scaleCommentService->getList(
            1,
            0,
            null,
            null,
            ['anr' => $this->anr->getId(), 'scale' => $threatsScale['id']]
        );
        $vulnsScale = current(current($this->scaleService->getList(
            1,
            0,
            null,
            null,
            ['anr' => $this->anr->getId(), 'type' => 3]
        )));
        $vulnsComments = $this->scaleCommentService->getList(
            1,
            0,
            null,
            null,
            ['anr' => $this->anr->getId(), 'scale' => $vulnsScale['id']]
        );

        $opRisksAllScales = $this->operationalRiskScaleService->getOperationalRiskScales($this->anr->getId());
        $opRisksImpactsScaleType = array_values(array_filter($opRisksAllScales, function ($scale) {
            return $scale['type'] === OperationalRiskScaleSuperClass::TYPE_IMPACT;
        }));
        $opRisksImpactsScaleMin = $opRisksImpactsScaleType[0]['min'];
        $opRisksImpactsScaleMax = $opRisksImpactsScaleType[0]['max'];
        $opRisksImpactsScales = array_values(array_filter($opRisksImpactsScaleType[0]['scaleTypes'], function ($scale) {
            return $scale['isHidden'] === false;
        }));
        $opRisksLikelihoodScale = array_values(array_filter($opRisksAllScales, function ($scale) {
            return $scale['type'] == OperationalRiskScaleSuperClass::TYPE_LIKELIHOOD;
        }))[0];

        // TODO : replace with $anr getters....
        $values = [
            'xml' => [
                'CONTEXT_ANA_RISK' => $this->generateWordXmlFromHtml(_WT($this->anr->contextAnaRisk)),
                'CONTEXT_GEST_RISK' => $this->generateWordXmlFromHtml(_WT($this->anr->contextGestRisk)),
                'SYNTH_EVAL_THREAT' => $this->generateWordXmlFromHtml(_WT($this->anr->synthThreat)),
            ],
            'table' => [
                'SCALE_IMPACT' => $this->generateInformationalRiskImpactsTable(
                    $impactsScale,
                    $impactsTypes,
                    $impactsComments
                ),
                'SCALE_THREAT' => $this->generateThreatOrVulnerabilityScaleTable(
                    $threatsScale,
                    $threatsComments
                ),
                'SCALE_VULN' => $this->generateThreatOrVulnerabilityScaleTable(
                    $vulnsScale,
                    $vulnsComments
                ),
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
                'TABLE_THREATS' => $this->generateThreatsTable(false),
                'TABLE_EVAL_TEND' => $this->generateTrendAssessmentTable(),
                'TABLE_THREATS_FULL' => $this->generateThreatsTable(true),
                'TABLE_INTERVIEW' => $this->generateInterviewsTable(),
            ],
        ];

        return $values;
    }

    /**
     * Build values for Step 2 deliverable (context modeling)
     * @return array The key-value array
     */
    protected function buildContextModelingValues()
    {
        // Models are incremental, so use values from level-1 model
        $values = $this->buildContextValidationValues();

        $values['xml']['SYNTH_ACTIF'] = $this->generateWordXmlFromHtml(_WT($this->anr->synthAct));
        $values['table']['IMPACTS_APPRECIATION'] = $this->generateImpactsAppreciation();

        return $values;
    }

    /**
     * Build values for Step 3 deliverable (risk assessment)
     * @return array The key-value array
     */
    protected function buildRiskAssessmentValues()
    {
        // Models are incremental, so use values from level-2 model
        $values = $this->buildContextModelingValues();

        $values = array_merge_recursive(
            $values,
            ['chart' => [
                'GRAPH_EVAL_RISK' => $this->generateRisksGraph(),
                'GRAPH_EVAL_OP_RISK' => $this->generateRisksGraph(false),
            ]]
        );

        $values = array_merge_recursive(
            $values,
            ['table' => [
                'RISKS_RECO_FULL' => $this->generateRisksPlan(),
                'OPRISKS_RECO_FULL' => $this->generateOperationalRisksPlan(),
                'TABLE_RISK_OWNERS' => $this->generateOwnersTable(),
            ]]
        );

        $values = array_merge_recursive(
            $values,
            ['xml' => [
                'DISTRIB_EVAL_RISK' => $this->generateWordXmlFromHtml(_WT($this->getRisksDistribution())),
                'DISTRIB_EVAL_OP_RISK' => $this->generateWordXmlFromHtml(_WT($this->getRisksDistribution(false))),
                'CURRENT_RISK_MAP' => $this->generateCurrentRiskMap('real'),
                'TARGET_RISK_MAP' => $this->generateCurrentRiskMap('targeted'),
                'TABLE_ASSET_CONTEXT' => $this->generateAssetContextTable(),
                'RISKS_KIND_OF_TREATMENT' => $this->generateRisksByKindOfTreatment(),
                'TABLE_AUDIT_INSTANCES' => $this->generateTableAudit(),
                'TABLE_AUDIT_RISKS_OP' => $this->generateTableAuditOp(),
            ]]
        );

        return $values;
    }

    /**
     * Build values for Step 4 deliverable (Implementation plan)
     * @return array The key-value array
     */
    protected function buildImplementationPlanValues()
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
    protected function buildStatementOfAppplicabilityValues($referential, $risksByControl)
    {
        $values = [];
        $soaScaleComments = array_filter(
            $this->soaScaleCommentTable->findByAnrOrderByIndex($this->anr),
            function ($soaScaleComment) {
                return !$soaScaleComment->isHidden();
            }
        );
        $translations = $this->translationTable->findByAnrTypesAndLanguageIndexedByKey(
            $this->anr,
            [Translation::SOA_SCALE_COMMENT],
            $this->configService->getActiveLanguageCodes()[$this->anr->getLanguage()]
        );

        $values['table']['TABLE_STATEMENT_OF_APPLICABILITY_SCALE'] = $this->generateTableStatementOfApplicabilityScale(
            $soaScaleComments,
            $translations
        );
        $values['table']['TABLE_STATEMENT_OF_APPLICABILITY'] = $this->generateTableStatementOfApplicability(
            $referential,
            $translations
        );
        if ($risksByControl) {
            $values['xml']['TABLE_RISKS_BY_CONTROL'] = $this->generateTableRisksByControl($referential);
        } else {
            $values['txt']['TABLE_RISKS_BY_CONTROL'] = null;
        }

        return $values;
    }

    /**
     * Build values for Step 5 deliverable (Record of Processing Activities)
     * @return array The key-value array
     */
    protected function buildRecordOfProcessingActivitiesValues($record)
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
    protected function buildAllRecordsValues()
    {
        $values['xml']['TABLE_ALL_RECORDS'] = $this->generateTableAllRecordsGDPR();

        return $values;
    }

    /**
     * Generate Informational Risk Impacts table
     * @return Table
     */
    protected function generateInformationalRiskImpactsTable($impactsScale, $impactsTypes, $impactsComments)
    {
        $tableWord = new PhpWord();
        $section = $tableWord->addSection();
        $table = $section->addTable($this->borderTable);

        $table->addRow(400, $this->tblHeader);
        $table->addCell(Converter::cmToTwip(2.00), $this->restartAndGrayCell)
            ->addText(
                $this->anrTranslate('Level'),
                $this->boldFont,
                $this->centerParagraph
            );
        $table->addCell(Converter::cmToTwip(8.40), $this->setColSpanCell(3, 'DFDFDF'))
            ->addText(
                $this->anrTranslate('Impact'),
                $this->boldFont,
                $this->centerParagraph
            );
        $table->addCell(Converter::cmToTwip(8.60), $this->restartAndGrayCell)
            ->addText(
                $this->anrTranslate('Consequences'),
                $this->boldFont,
                $this->centerParagraph
            );

        // Manually add C/I/D impacts columns
        $table->addRow();
        $table->addCell(Converter::cmToTwip(2.00), $this->continueAndGrayCell);
        $table->addCell(Converter::cmToTwip(2.80), $this->grayCell)
            ->addText(
                $this->anrTranslate('Confidentiality'),
                $this->boldFont,
                $this->centerParagraph
            );
        $table->addCell(Converter::cmToTwip(2.80), $this->grayCell)
            ->addText(
                $this->anrTranslate('Integrity'),
                $this->boldFont,
                $this->centerParagraph
            );
        $table->addCell(Converter::cmToTwip(2.80), $this->grayCell)
            ->addText(
                $this->anrTranslate('Availability'),
                $this->boldFont,
                $this->centerParagraph
            );
        $table->addCell(Converter::cmToTwip(8.60), $this->continueAndGrayCell);

        // Fill in each row
        for ($row = $impactsScale['min']; $row <= $impactsScale['max']; ++$row) {
            $table->addRow(400);
            $table->addCell(Converter::cmToTwip(2.00), $this->restartAndTopCell)
                ->addText(
                    $row,
                    $this->normalFont,
                    $this->centerParagraph
                );

            $impactsTypePerType = [];

            foreach ($impactsTypes as $impactType) {
                $impactsTypePerType[$impactType['type_id']] = $impactType;
            }

            // Put C/I/D first
            for ($i = 1; $i <= 3; ++$i) {
                $impactType = $impactsTypePerType[$i];

                // Find the appropriate comment
                $commentText = '';
                foreach ($impactsComments as $comment) {
                    if ($comment['scaleImpactType']->id == $impactType['id'] && $comment['scaleIndex'] == $row) {
                        $commentText = $comment['comment' . $this->currentLangAnrIndex];
                        break;
                    }
                }

                $table->addCell(Converter::cmToTwip(2.80), $this->restartAndTopCell)
                    ->addText(
                        _WT($commentText),
                        $this->normalFont,
                        $this->leftParagraph
                    );
            }

            // Then ROLFP and custom columns as rows
            $first = true;
            foreach ($impactsTypes as $impactType) {
                if ($impactType['type_id'] < 4 || $impactType['isHidden']) {
                    continue;
                }

                if ($first) {
                    $first = false;
                } else {
                    $table->addRow(400);
                    $table->addCell(Converter::cmToTwip(2.15), $this->continueCell);
                    $table->addCell(Converter::cmToTwip(2.15), $this->continueCell);
                    $table->addCell(Converter::cmToTwip(2.15), $this->continueCell);
                    $table->addCell(Converter::cmToTwip(2.15), $this->continueCell);
                }

                // Find the appropriate comment
                $commentText = '';
                foreach ($impactsComments as $comment) {
                    if ($comment['scaleImpactType']->id == $impactType['id'] && $comment['scaleIndex'] == $row) {
                        $commentText = $comment['comment' . $this->currentLangAnrIndex];
                        break;
                    }
                }

                $cellConsequences = $table->addCell(Converter::cmToTwip(2.80), $this->vAlignCenterCell);
                $cellConsequencesRun = $cellConsequences->addTextRun($this->leftParagraph);
                $cellConsequencesRun->addText(
                    _WT($this->anrTranslate($impactType['label' . $this->currentLangAnrIndex])) . ' : ',
                    $this->boldFont
                );
                $cellConsequencesRun->addText(
                    _WT($commentText),
                    $this->normalFont
                );
            }
        }
        return $table;
    }

    /**
     * Generate Informational Risk Acceptance thresholds table
     * @return Table
     */
    protected function generateInformationalRiskAcceptanceThresholdsTable($impactsScale, $threatsScale, $vulnsScale)
    {
        $tableWord = new PhpWord();
        $section = $tableWord->addSection();
        $table = $section->addTable($this->noBorderTable);

        $header = [];
        for ($t = $threatsScale['min']; $t <= $threatsScale['max']; ++$t) {
            for ($v = $vulnsScale['min']; $v <= $vulnsScale['max']; ++$v) {
                $prod = $t * $v;
                if (array_search($prod, $header) === false) {
                    $header[] = $prod;
                }
            }
        }
        asort($header);

        $size = 13 / (count($header) + 2); // 15cm
        $table->addRow();
        $table->addCell(null, $this->setColSpanCell(2));
        $table->addCell(null, $this->setColSpanCell(count($header)))
            ->addText(
                $this->anrTranslate('TxV'),
                $this->boldFont,
                $this->centerParagraph
            );
        $table->addRow();
        $table->addCell(null, $this->rotate90TextCell)
            ->addText(
                $this->anrTranslate('Impact'),
                $this->boldFont,
                $this->centerParagraph
            );
        $table->addCell(null, $this->whiteBigBorderTable);
        foreach ($header as $MxV) {
            $table->addCell(Converter::cmToTwip(1), $this->whiteBigBorderTable)
                ->addText(
                    $MxV,
                    $this->boldFont,
                    $this->centerParagraph
                );
        }

        for ($row = $impactsScale['min']; $row <= $impactsScale['max']; ++$row) {
            $table->addRow(Converter::cmToTwip($size));
            $table->addCell(null, $this->continueCell);
            $table->addCell(Converter::cmToTwip(1), $this->whiteBigBorderTable)
                ->addText(
                    $row,
                    $this->boldFont,
                    $this->centerParagraph
                );

            foreach ($header as $MxV) {
                $value = $MxV * $row;

                $style = array_merge($this->whiteBigBorderTable, $this->setBgColorCell($value));
                $table->addCell(null, $style)
                    ->addText(
                        $value,
                        $this->boldFont,
                        $this->centerParagraph
                    );
            }
        }

        return $table;
    }

    /**
     * Generate Operational Risk Acceptance thresholds Table
     * @return Table
     */
    protected function generateOperationalRiskImpactsTable(
        $opRisksImpactsScales,
        $opRisksImpactsScaleMin,
        $opRisksImpactsScaleMax
    ) {
        $tableWord = new PhpWord();
        $section = $tableWord->addSection();
        $table = $section->addTable($this->borderTable);

        $sizeColumn = 17 / count($opRisksImpactsScales);

        $table->addRow(400, $this->tblHeader);
        $table->addCell(Converter::cmToTwip(2.00), $this->grayCell)
            ->addText(
                $this->anrTranslate('Level'),
                $this->boldFont,
                $this->centerParagraph
            );
        foreach ($opRisksImpactsScales as $opRiskImpactScale) {
            $table->addCell(Converter::cmToTwip($sizeColumn), $this->grayCell)
                ->addText(
                    _WT($opRiskImpactScale['label']),
                    $this->boldFont,
                    $this->centerParagraph
                );
        }

        for ($row = $opRisksImpactsScaleMin; $row <= $opRisksImpactsScaleMax; ++$row) {
            $table->addRow(400);
            $table->addCell(Converter::cmToTwip(2.00), $this->restartAndTopCell)
                ->addText(
                    $opRisksImpactsScales[0]['comments'][$row]['scaleValue'],
                    $this->normalFont,
                    $this->centerParagraph
                );
            foreach ($opRisksImpactsScales as $opRiskImpactScale) {
                  $table->addCell(Converter::cmToTwip($sizeColumn), $this->restartAndTopCell)
                    ->addText(
                        _WT($opRiskImpactScale['comments'][$row]['comment']),
                        $this->normalFont,
                        $this->leftParagraph
                    );
            }
        }

        return $table;
    }

    /**
     * Generate Operational Risk Likelihood Table
     * @return Table
     */
    protected function generateOperationalRiskLikelihoodTable($opRisksLikelihoodScale)
    {
        $tableWord = new PhpWord();
        $section = $tableWord->addSection();
        $table = $section->addTable($this->borderTable);

        $table->addRow(400, $this->tblHeader);
        $table->addCell(Converter::cmToTwip(2.00), $this->grayCell)
            ->addText(
                $this->anrTranslate('Level'),
                $this->boldFont,
                $this->centerParagraph
            );
        $table->addCell(Converter::cmToTwip(16.00), $this->grayCell)
            ->addText(
                $this->anrTranslate('Comment'),
                $this->boldFont,
                $this->centerParagraph
            );

        foreach ($opRisksLikelihoodScale['comments'] as $comment) {
            $table->addRow(400);
            $table->addCell(Converter::cmToTwip(2.00), $this->vAlignCenterCell)
                ->addText(
                    $comment['scaleValue'],
                    $this->normalFont,
                    $this->centerParagraph
                );
            $table->addCell(Converter::cmToTwip(16.00), $this->vAlignCenterCell)
                ->addText(
                    _WT($comment['comment']),
                    $this->normalFont,
                    $this->leftParagraph
                );
        }

        return $table;
    }

    /**
     * Generate Operational Risk Acceptance thresholds Table
     * @return Table
     */
    protected function generateOperationalRiskAcceptanceThresholdsTable(
        $opRisksImpactsScales,
        $opRisksLikelihoodScale,
        $opRisksImpactsScaleMin,
        $opRisksImpactsScaleMax
    ) {
        $tableWord = new PhpWord();
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
            ->addText(
                $this->anrTranslate('Probability'),
                $this->boldFont,
                $this->centerParagraph
            );
        $table->addRow();
        $table->addCell(null, $this->rotate90TextCell)
            ->addText(
                $this->anrTranslate('Impact'),
                $this->boldFont,
                $this->centerParagraph
            );
        $table->addCell(null, $this->whiteBigBorderTable);
        foreach ($header as $Prob) {
            $table->addCell(Converter::cmToTwip($size), $this->whiteBigBorderTable)
                ->addText(
                    $Prob,
                    $this->boldFont,
                    $this->centerParagraph
                );
        }

        for ($row = $opRisksImpactsScaleMin; $row <= $opRisksImpactsScaleMax; ++$row) {
            $impactValue = $opRisksImpactsScales[0]['comments'][$row]['scaleValue'];
            $table->addRow(Converter::cmToTwip($size));
            $table->addCell(null, $this->continueCell);
            $table->addCell(Converter::cmToTwip($size), $this->whiteBigBorderTable)
                ->addText(
                    $impactValue,
                    $this->boldFont,
                    $this->centerParagraph
                );
            foreach ($header as $Prob) {
                $value = $Prob * $impactValue;
                $style = array_merge($this->whiteBigBorderTable, $this->setBgColorCell($value, false));
                $table->addCell(null, $style)
                    ->addText(
                        $value,
                        $this->boldFont,
                        $this->centerParagraph
                    );
            }
        }

        return $table;
    }

    /**
     * Generate Trends Assessment Table
     * @return Table
     */
    protected function generateTrendAssessmentTable()
    {
        $questions = $this->questionService->getList(1, 0, null, null, ['anr' => $this->anr->getId()]);
        $questionsChoices = $this->questionChoiceService->getList(1, 0, null, null, ['anr' => $this->anr->getId()]);

        $tableWord = new PhpWord();
        $section = $tableWord->addSection();
        $table = $section->addTable($this->noBorderTable);

        // Fill in each row
        foreach ($questions as $question) {
            $response = null;
            if ($question['type'] == 1) {
                // Simple text
                $response = $question['response'];
            } else {
                // Choice, either simple or multiple
                if ($question['multichoice']) {
                    $responseIds = json_decode($question['response'], true);
                    $responses = [];

                    foreach ($questionsChoices as $choice) {
                        if (!is_null($responseIds) && array_search($choice['id'], $responseIds) !== false) {
                            $responses[] = '- ' . $choice['label' . $this->currentLangAnrIndex];
                        }
                    }

                    $response = join("\n", $responses);
                } else {
                    foreach ($questionsChoices as $choice) {
                        if ($choice['id'] == $question['response']) {
                            $response = $choice['label' . $this->currentLangAnrIndex];
                            break;
                        }
                    }
                }
            }

            // no display question, if reply is empty
            if (!empty($response)) {
                $table->addRow(400);
                $table->addCell(Converter::cmToTwip(18.00), $this->vAlignCenterCell)
                    ->addText(
                        _WT($question['label' . $this->currentLangAnrIndex]),
                        $this->boldFont,
                        $this->leftParagraph
                    );
                $table->addRow(400);
                $table->addCell(Converter::cmToTwip(18.00), $this->vAlignCenterCell)
                    ->addText(
                        _WT($response),
                        $this->normalFont,
                        $this->leftParagraph
                    );
            }
        }

        return $table;
    }

    /**
     * Generate Interviews Table
     * @return Table
     */
    protected function generateInterviewsTable()
    {
        $interviews = $this->interviewService->getList(1, 0, null, null, ['anr' => $this->anr->getId()]);

        $tableWord = new PhpWord();
        $section = $tableWord->addSection();
        $table = $section->addTable($this->borderTable);

        if (count($interviews)) {
            $table->addRow(400, $this->tblHeader);

            $table->addCell(Converter::cmToTwip(4.00), $this->grayCell)
                ->addText(
                    $this->anrTranslate("Date"),
                    $this->boldFont,
                    $this->centerParagraph
                );
            $table->addCell(Converter::cmToTwip(4.00), $this->grayCell)
                ->addText(
                    $this->anrTranslate("Department / People"),
                    $this->boldFont,
                    $this->centerParagraph
                );
            $table->addCell(Converter::cmToTwip(9.00), $this->grayCell)
                ->addText(
                    $this->anrTranslate("Contents"),
                    $this->boldFont,
                    $this->centerParagraph
                );
        }

        // Fill in each row
        foreach ($interviews as $interview) {
            $table->addRow(400);
            $table->addCell(Converter::cmToTwip(4.00), $this->vAlignCenterCell)
                ->addText(
                    _WT($interview['date']),
                    $this->normalFont,
                    $this->leftParagraph
                );
            $table->addCell(Converter::cmToTwip(4.00), $this->vAlignCenterCell)
                ->addText(
                    _WT($interview['service']),
                    $this->normalFont,
                    $this->leftParagraph
                );
            $table->addCell(Converter::cmToTwip(9.00), $this->vAlignCenterCell)
                ->addText(
                    _WT($interview['content']),
                    $this->normalFont,
                    $this->leftParagraph
                );
        }

        return $table;
    }

    /**
     * Generate Threat or Vulnerability scale table
     * @param array $scale
     * @param array $comments
     * @return Table
     */
    protected function generateThreatOrVulnerabilityScaleTable($scale, $comments)
    {
        $tableWord = new PhpWord();
        $section = $tableWord->addSection();
        $table = $section->addTable($this->borderTable);

        $table->addRow(400, $this->tblHeader);
        $table->addCell(Converter::cmToTwip(2.00), $this->grayCell)
            ->addText(
                $this->anrTranslate('Level'),
                $this->boldFont,
                $this->centerParagraph
            );
        $table->addCell(Converter::cmToTwip(16.00), $this->grayCell)
            ->addText(
                $this->anrTranslate('Comment'),
                $this->boldFont,
                $this->centerParagraph
            );

        // Fill in each row
        for ($row = $scale['min']; $row <= $scale['max']; ++$row) {
            $table->addRow(400);
            $table->addCell(Converter::cmToTwip(2.00), $this->vAlignCenterCell)
                ->addText(
                    $row,
                    $this->normalFont,
                    $this->centerParagraph
                );

            // Find the appropriate comment
            $commentText = '';
            foreach ($comments as $comment) {
                if ($comment['scaleIndex'] == $row) {
                    $commentText = $comment['comment' . $this->currentLangAnrIndex];
                    break;
                }
            }

            $table->addCell(Converter::cmToTwip(16.00), $this->vAlignCenterCell)
                ->addText(
                    _WT($commentText),
                    $this->normalFont,
                    $this->leftParagraph
                );
        }

        return $table;
    }

    /**
     * Generate Current Risk Map
     * @param string $type
     * @return string
     */
    protected function generateCurrentRiskMap($type = 'real')
    {
        $cartoRisk = ($type == 'real') ?
            $this->cartoRiskService->getCartoReal($this->anr->getId()) :
            $this->cartoRiskService->getCartoTargeted($this->anr->getId());

            // Generate risks table
            $tableWord = new PhpWord();
            $section = $tableWord->addSection();

        if (!empty($cartoRisk['riskInfo']['counters'])) {
            $section->addText(
                $this->anrTranslate('Information risks'),
                $this->boldFont,
                ['indent' => 0.5]
            );

            $params  = [
                'riskType' => 'riskInfo',
                'axisX' => 'MxV',
                'axisY' => 'Impact',
                'labelAxisX' => 'TxV',
                'thresholds' => [
                    $this->anr->seuil1,
                    $this->anr->seuil2
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
            $params  = [
                'riskType' => 'riskOp',
                'axisX' => 'Likelihood',
                'axisY' => 'OpRiskImpact',
                'labelAxisX' => 'Probability',
                'thresholds' => [
                    $this->anr->seuilRolf1,
                    $this->anr->seuilRolf2
                ],
            ];
            $section = $this->generateCartographyMap($cartoRisk, $section, $params);
        }

            return $this->getWordXmlFromWordObject($tableWord);
    }

    /**
     * Generate Cartography Map
     * @param $data
     * @param object $section
     * @param object $params
     *
     * @return object
     */
    protected function generateCartographyMap($data, $section, $params)
    {
        $axisX = $data[$params['axisX']];
        $axisY = $data[$params['axisY']];
        $labelAxisX = $params['labelAxisX'];
        $data = $data[$params['riskType']]['counters'];
        $thresholds = $params['thresholds'];
        $size = 0.75;

        $table = $section->addTable($this->noBorderTable);
        $table->addRow(Converter::cmToTwip($size));
        $table->addCell(null, $this->setColSpanCell(2));
        $table->addCell(null, $this->setColSpanCell(count($axisX)))
            ->addText(
                $this->anrTranslate($labelAxisX),
                $this->boldFont,
                $this->centerParagraph
            );
        $table->addRow(Converter::cmToTwip($size));
        $table->addCell(null, $this->rotate90TextCell)
            ->addText(
                $this->anrTranslate('Impact'),
                $this->boldFont,
                $this->centerParagraph
            );
        $table->addCell(Converter::cmToTwip($size), $this->whiteBigBorderTable);

        foreach ($axisX as $x) {
            $table->addCell(Converter::cmToTwip($size), $this->whiteBigBorderTable)
            ->addText(
                $x,
                $this->boldFont,
                $this->centerParagraph
            );
        }

        //row
        $nbLow = 0;
        $nbMedium = 0;
        $nbHigh = 0;
        foreach ($axisY as $y) {
            $table->addRow(Converter::cmToTwip($size));
            $table->addCell(null, $this->continueCell);
            $table->addCell(Converter::cmToTwip($size), $this->whiteBigBorderTable)
                ->addText(
                    $y,
                    $this->boldFont,
                    $this->centerParagraph
                );

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
                } else {
                    if ($value <= $thresholds[1]) {
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
                }
                $table->addCell(Converter::cmToTwip($size), $style)
                    ->addText(
                        $result,
                        $this->boldFont,
                        $this->centerParagraph
                    );
            }
        }

        //legend
        $maxSize = 7;
        $total = $nbLow + $nbMedium + $nbHigh;
        $lowSize = ($total) ? ($maxSize * $nbLow) / $total : 0;
        $mediumSize = ($total) ? ($maxSize * $nbMedium) / $total : 0;
        $highSize = ($total) ? ($maxSize * $nbHigh) / $total : 0;

        $section->addTextBreak(1);

        $tableLegend = $section->addTable();
        $tableLegend->addRow(Converter::cmToTwip(0.1));
        $tableLegend->addCell(Converter::cmToTwip(0.5), $this->continueCell);
        $tableLegend->addCell(Converter::cmToTwip(5), $this->whiteBigBorderTable)
            ->addText(
                $nbLow . ' ' . $this->anrTranslate('Low risks'),
                $this->boldFont,
                $this->leftParagraph
            );
        if ($lowSize > 0) {
            $style = array_merge(
                $this->whiteBigBorderTable,
                ['BgColor' => 'D6F107', 'BorderTopSize' => 0, 'BorderBottomSize' => 30]
            );
            unset($style['BorderSize']);
            $tableLegend->addCell(Converter::cmToTwip($lowSize), $style);
        }

        if (($maxSize - $lowSize) != 0) {
            $style['BgColor'] = 'F0F7B2';
            $tableLegend->addCell(Converter::cmToTwip($maxSize - $lowSize), $style);
        }

        $tableLegend = $section->addTable();
        $tableLegend->addRow(Converter::cmToTwip(0.1));
        $tableLegend->addCell(Converter::cmToTwip(0.5), $this->continueCell);
        $tableLegend->addCell(Converter::cmToTwip(5), $this->whiteBigBorderTable)
            ->addText(
                $nbMedium . ' ' . $this->anrTranslate('Medium risks'),
                $this->boldFont,
                $this->leftParagraph
            );
        if ($mediumSize > 0) {
            $style = array_merge(
                $this->whiteBigBorderTable,
                ['BgColor' => 'FFBC1C', 'BorderTopSize' => 50, 'BorderBottomSize' => 30]
            );
            unset($style['BorderSize']);
            $tableLegend->addCell(Converter::cmToTwip($mediumSize), $style);
        }

        if (($maxSize - $mediumSize) != 0) {
            $style['BgColor'] = 'FCDD94';
            $tableLegend->addCell(Converter::cmToTwip($maxSize - $mediumSize), $style);
        }

        $tableLegend = $section->addTable();
        $tableLegend->addRow(Converter::cmToTwip(0.1));
        $tableLegend->addCell(Converter::cmToTwip(0.5), $this->continueCell);
        $tableLegend->addCell(Converter::cmToTwip(5), $this->whiteBigBorderTable)
            ->addText(
                $nbHigh . ' ' . $this->anrTranslate('High risks'),
                $this->boldFont,
                $this->leftParagraph
            );
        if ($highSize > 0) {
            $style = array_merge(
                $this->whiteBigBorderTable,
                ['BgColor' => 'FD661F', 'BorderTopSize' => 50, 'BorderBottomSize' => 30]
            );
            unset($style['BorderSize']);
            $tableLegend->addCell(Converter::cmToTwip($highSize), $style);
        }

        if (($maxSize - $highSize) != 0) {
            $style['BgColor'] = 'FCB28F';
            $tableLegend->addCell(Converter::cmToTwip($maxSize - $highSize), $style);
        }

        return $section;
    }

    /**
     * Generates the risks graph that is included in the model
     * @return array An array with the path and details of the generated canvas
     */
    protected function generateRisksGraph($infoRisk = true)
    {
        $this->cartoRiskService->buildListScalesAndHeaders($this->anr->getId());
        [$counters, $distrib] = $infoRisk ?
            $this->cartoRiskService->getCountersRisks('raw') :
            $this->cartoRiskService->getCountersOpRisks('raw') ;

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

        $PhpWord = new PhpWord();
        $section = $PhpWord->addSection();
        $chart = $section->addChart(
            'column',
            $categories,
            $series,
            $this->barChart
        );

        return $chart;
    }

    /**
     * Generate the audit table data
     * @return mixed|string The generated WordXml data
     */
    protected function generateTableAudit()
    {
        $instanceRisks = $this->instanceRiskTable->findByAnrAndOrderByParams($this->anr, ['ir.cacheMaxRisk' => 'DESC']);

        $mem_risks = $globalObject = [];
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
                        $levelTree = \count($asc);
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
                    'kindOfMeasure' => $instanceRisk->getKindOfMeasure(),
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

            $tableWord = new PhpWord();
            $section = $tableWord->addSection();
            for ($i = 0; $i < $maxLevelDeep + 1; $i++) {
                $tableWord->addTitleStyle($i + 3, $this->titleFont);
            }

            if (in_array('true', $global)) {
                $section->addTitle(
                    $this->anrTranslate('Global assets'),
                    3
                );
            }

            foreach ($mem_risks as $data) {
                if (empty($data['tree'])) {
                    $section->addTitle(
                        _WT($data['ctx']),
                        4
                    );
                    $table = $section->addTable($this->borderTable);
                    $table->addRow(400, $this->tblHeader);
                    $table->addCell(Converter::cmToTwip(2.10), $this->setColSpanCell(3, '444444'))
                        ->addText(
                            $this->anrTranslate('Impact'),
                            $this->whiteFont,
                            $this->centerParagraph
                        );
                    $table->addCell(Converter::cmToTwip(5.70), $this->setColSpanCell(2, '444444'))
                        ->addText(
                            $this->anrTranslate('Threat'),
                            $this->whiteFont,
                            $this->centerParagraph
                        );
                    $table->addCell(Converter::cmToTwip(10.70), $this->setColSpanCell(3, '444444'))
                        ->addText(
                            $this->anrTranslate('Vulnerability'),
                            $this->whiteFont,
                            $this->centerParagraph
                        );
                    $table->addCell(Converter::cmToTwip(2.10), $this->setColSpanCell(3, '444444'))
                        ->addText(
                            $this->anrTranslate('Current risk'),
                            $this->whiteFont,
                            $this->centerParagraph
                        );
                    $table->addCell(Converter::cmToTwip(1.00), $this->restartAndBlackCell)
                        ->addText(
                            $this->anrTranslate('Treatment'),
                            $this->whiteFont,
                            $this->centerParagraph
                        );
                    $table->addCell(Converter::cmToTwip(1.00), $this->restartAndBlackCell)
                        ->addText(
                            $this->anrTranslate('Residual risk'),
                            $this->whiteFont,
                            $this->centerParagraph
                        );

                    $table->addRow(400, $this->tblHeader);
                    $table->addCell(Converter::cmToTwip(0.70), $this->blackCell)
                        ->addText(
                            'C',
                            $this->whiteFont,
                            $this->centerParagraph
                        );
                    $table->addCell(Converter::cmToTwip(0.70), $this->blackCell)
                        ->addText(
                            'I',
                            $this->whiteFont,
                            $this->centerParagraph
                        );
                    $table->addCell(Converter::cmToTwip(0.70), $this->blackCell)
                        ->addText(
                            $this->anrTranslate('A'),
                            $this->whiteFont,
                            $this->centerParagraph
                        );
                    $table->addCell(Converter::cmToTwip(5.00), $this->blackCell)
                        ->addText(
                            $this->anrTranslate('Label'),
                            $this->whiteFont,
                            $this->centerParagraph
                        );
                    $table->addCell(Converter::cmToTwip(0.70), $this->blackCell)
                        ->addText(
                            $this->anrTranslate('Prob.'),
                            $this->whiteFont,
                            $this->centerParagraph
                        );
                    $table->addCell(Converter::cmToTwip(5.00), $this->blackCell)
                        ->addText(
                            $this->anrTranslate('Label'),
                            $this->whiteFont,
                            $this->centerParagraph
                        );
                    $table->addCell(Converter::cmToTwip(5.00), $this->blackCell)
                        ->addText(
                            $this->anrTranslate('Existing controls'),
                            $this->whiteFont,
                            $this->centerParagraph
                        );
                    $table->addCell(Converter::cmToTwip(0.70), $this->blackCell)
                        ->addText(
                            $this->anrTranslate('Qualif.'),
                            $this->whiteFont,
                            $this->centerParagraph
                        );
                    $table->addCell(Converter::cmToTwip(0.70), $this->blackCell)
                        ->addText(
                            'C',
                            $this->whiteFont,
                            $this->centerParagraph
                        );
                    $table->addCell(Converter::cmToTwip(0.70), $this->blackCell)
                        ->addText(
                            'I',
                            $this->whiteFont,
                            $this->centerParagraph
                        );
                    $table->addCell(Converter::cmToTwip(0.70), $this->blackCell)
                        ->addText(
                            $this->anrTranslate('A'),
                            $this->whiteFont,
                            $this->centerParagraph
                        );
                    $table->addCell(Converter::cmToTwip(1.00), $this->continueAndBlackCell);
                    $table->addCell(Converter::cmToTwip(1.00), $this->continueAndBlackCell);
                } else {
                    for ($i = 0; $i < count($data['tree']); $i++) {
                        if ($i <= $maxLevelTitle - 1 && $title[$i] != $data['tree'][$i]['id']) {
                            $section->addTitle(
                                _WT($data['tree'][$i]['name' . $this->currentLangAnrIndex]),
                                $i + 3
                            );
                            $title[$i] = $data['tree'][$i]['id'];
                            if ($maxLevelTitle == count($data['tree']) && empty($data['risks'])) {
                                $data['risks'] = true;
                            }
                            if ($i == count($data['tree']) - 1 && !empty($data['risks'])) {
                                $section->addTextBreak();
                                $table = $section->addTable($this->borderTable);
                                $table->addRow(400, $this->tblHeader);
                                $table->addCell(Converter::cmToTwip(2.10), $this->setColSpanCell(3, '444444'))
                                    ->addText(
                                        $this->anrTranslate('Impact'),
                                        $this->whiteFont,
                                        $this->centerParagraph
                                    );
                                $table->addCell(Converter::cmToTwip(5.70), $this->setColSpanCell(2, '444444'))
                                    ->addText(
                                        $this->anrTranslate('Threat'),
                                        $this->whiteFont,
                                        $this->centerParagraph
                                    );
                                $table->addCell(Converter::cmToTwip(10.70), $this->setColSpanCell(3, '444444'))
                                    ->addText(
                                        $this->anrTranslate('Vulnerability'),
                                        $this->whiteFont,
                                        $this->centerParagraph
                                    );
                                $table->addCell(Converter::cmToTwip(2.10), $this->setColSpanCell(3, '444444'))
                                    ->addText(
                                        $this->anrTranslate('Current risk'),
                                        $this->whiteFont,
                                        $this->centerParagraph
                                    );
                                $table->addCell(Converter::cmToTwip(1.00), $this->restartAndBlackCell)
                                    ->addText(
                                        $this->anrTranslate('Treatment'),
                                        $this->whiteFont,
                                        $this->centerParagraph
                                    );
                                $table->addCell(Converter::cmToTwip(1.00), $this->restartAndBlackCell)
                                    ->addText(
                                        $this->anrTranslate('Residual risk'),
                                        $this->whiteFont,
                                        $this->centerParagraph
                                    );

                                $table->addRow(400, $this->tblHeader);
                                $table->addCell(Converter::cmToTwip(0.70), $this->blackCell)
                                    ->addText(
                                        'C',
                                        $this->whiteFont,
                                        $this->centerParagraph
                                    );
                                $table->addCell(Converter::cmToTwip(0.70), $this->blackCell)
                                    ->addText(
                                        'I',
                                        $this->whiteFont,
                                        $this->centerParagraph
                                    );
                                $table->addCell(Converter::cmToTwip(0.70), $this->blackCell)
                                    ->addText(
                                        $this->anrTranslate('A'),
                                        $this->whiteFont,
                                        $this->centerParagraph
                                    );
                                $table->addCell(Converter::cmToTwip(5.00), $this->blackCell)
                                    ->addText(
                                        $this->anrTranslate('Label'),
                                        $this->whiteFont,
                                        $this->centerParagraph
                                    );
                                $table->addCell(Converter::cmToTwip(0.70), $this->blackCell)
                                    ->addText(
                                        $this->anrTranslate('Prob.'),
                                        $this->whiteFont,
                                        $this->centerParagraph
                                    );
                                $table->addCell(Converter::cmToTwip(5.00), $this->blackCell)
                                    ->addText(
                                        $this->anrTranslate('Label'),
                                        $this->whiteFont,
                                        $this->centerParagraph
                                    );
                                $table->addCell(Converter::cmToTwip(5.00), $this->blackCell)
                                    ->addText(
                                        $this->anrTranslate('Existing controls'),
                                        $this->whiteFont,
                                        $this->centerParagraph
                                    );
                                $table->addCell(Converter::cmToTwip(0.70), $this->blackCell)
                                    ->addText(
                                        $this->anrTranslate('Qualif.'),
                                        $this->whiteFont,
                                        $this->centerParagraph
                                    );
                                $table->addCell(Converter::cmToTwip(0.70), $this->blackCell)
                                    ->addText(
                                        'C',
                                        $this->whiteFont,
                                        $this->centerParagraph
                                    );
                                $table->addCell(Converter::cmToTwip(0.70), $this->blackCell)
                                    ->addText(
                                        'I',
                                        $this->whiteFont,
                                        $this->centerParagraph
                                    );
                                $table->addCell(Converter::cmToTwip(0.70), $this->blackCell)
                                    ->addText(
                                        $this->anrTranslate('A'),
                                        $this->whiteFont,
                                        $this->centerParagraph
                                    );
                                $table->addCell(Converter::cmToTwip(1.00), $this->continueAndBlackCell);
                                $table->addCell(Converter::cmToTwip(1.00), $this->continueAndBlackCell);
                            }
                        }
                    }
                }

                if (!empty($data['risks']) && $data['risks'] !== true) {
                    if ($data['global'] == false) {
                        $table = $section->addTable($this->borderTable);
                        $table->addRow(400);
                        $table->addCell(Converter::cmToTwip(19.00), $this->setColSpanCell(13, 'DFDFDF'))
                            ->addText(
                                _WT($data['ctx']),
                                $this->boldFont,
                                $this->leftParagraph
                            );
                    }
                    foreach ($data['risks'] as $r) {
                        foreach ($r as $key => $value) {
                            if ($value == -1) {
                                $r[$key] = '-';
                            }
                        }

                        $table->addRow(400);
                        $table->addCell(Converter::cmToTwip(0.70), $this->vAlignCenterCell)
                            ->addText(
                                $r['impactC'],
                                $this->normalFont,
                                $this->centerParagraph
                            );
                        $table->addCell(Converter::cmToTwip(0.70), $this->vAlignCenterCell)
                            ->addText(
                                $r['impactI'],
                                $this->normalFont,
                                $this->centerParagraph
                            );
                        $table->addCell(Converter::cmToTwip(0.70), $this->vAlignCenterCell)
                            ->addText(
                                $r['impactA'],
                                $this->normalFont,
                                $this->centerParagraph
                            );
                        $table->addCell(Converter::cmToTwip(5.00), $this->vAlignCenterCell)
                            ->addText(
                                _WT($r['threat']),
                                $this->normalFont,
                                $this->leftParagraph
                            );
                        $table->addCell(Converter::cmToTwip(0.70), $this->vAlignCenterCell)
                            ->addText(
                                $r['threatRate'],
                                $this->normalFont,
                                $this->centerParagraph
                            );
                        $table->addCell(Converter::cmToTwip(5.00), $this->vAlignCenterCell)
                            ->addText(
                                _WT($r['vulnerability']),
                                $this->normalFont,
                                $this->leftParagraph
                            );
                        $table->addCell(Converter::cmToTwip(5.00), $this->vAlignCenterCell)
                            ->addText(
                                _WT($r['comment']),
                                $this->normalFont,
                                $this->leftParagraph
                            );
                        $table->addCell(Converter::cmToTwip(0.70), $this->vAlignCenterCell)
                            ->addText(
                                $r['vulRate'],
                                $this->normalFont,
                                $this->centerParagraph
                            );
                        $table->addCell(Converter::cmToTwip(0.70), $this->setBgColorCell($r['riskC']))
                            ->addText(
                                $r['riskC'],
                                $this->boldFont,
                                $this->centerParagraph
                            );
                        $table->addCell(Converter::cmToTwip(0.70), $this->setBgColorCell($r['riskI']))
                            ->addText(
                                $r['riskI'],
                                $this->boldFont,
                                $this->centerParagraph
                            );
                        $table->addCell(Converter::cmToTwip(0.70), $this->setBgColorCell($r['riskA']))
                            ->addText(
                                $r['riskA'],
                                $this->boldFont,
                                $this->centerParagraph
                            );
                        $table->addCell(Converter::cmToTwip(1.00), $this->vAlignCenterCell)
                            ->addText(
                                $this->getKindfofMeasureLabel($r['kindOfMeasure']),
                                $this->normalFont,
                                $this->leftParagraph
                            );
                        $table->addCell(Converter::cmToTwip(1.00), $this->setBgColorCell($r['targetRisk']))
                            ->addText(
                                $r['targetRisk'],
                                $this->boldFont,
                                $this->centerParagraph
                            );
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
                    'parent' => $parentInstance ? $parentInstance->getId() : null,
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
                'kindOfMeasure' => $operationalInstanceRisk->getKindOfMeasure(),
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
        usort($tree, function ($a, $b) {
            return $a['position'] <=> $b['position'];
        });
        foreach ($tree as $branch) {
            unset($branch['position']);
            $flat_array = $this->singleLevelArray($branch);
            $lst = array_merge($lst, $flat_array);
        }

        if (!empty($lst)) {
            $opRisksAllScales = $this->operationalRiskScaleService->getOperationalRiskScales($this->anr->getId());
            $opRisksImpactsScaleType = array_values(array_filter($opRisksAllScales, function ($scale) {
                return $scale['type'] == 1;
            }));
            $opRisksImpactsScales = array_filter($opRisksImpactsScaleType[0]['scaleTypes'], function ($scale) {
                return $scale['isHidden'] == false;
            });
            $sizeCellImpact = count($opRisksImpactsScales) * 0.70;

            $tableWord = new PhpWord();
            $section = $tableWord->addSection();
            $maxLevelDeep = ($maxLevelDeep <= 4 ? $maxLevelDeep : 4);
            for ($i=0; $i < $maxLevelDeep; $i++) {
                $tableWord->addTitleStyle($i + 3, $this->titleFont);
            }

            $maxLevelTitle = ($maxLevelDeep == 1 ? $maxLevelDeep : $maxLevelDeep - 1);

            $title = array_fill(0, $maxLevelDeep, null);

            foreach ($lst as $data) {
                for ($i = 0; $i < count($data['tree']); $i++) {
                    if ($i <= $maxLevelTitle - 1 && $title[$i] != $data['tree'][$i]['id']) {
                        $section->addTitle(
                            _WT($data['tree'][$i]['name' . $this->currentLangAnrIndex]),
                            $i + 3
                        );
                        $title[$i] = $data['tree'][$i]['id'];
                        if ($maxLevelTitle == count($data['tree']) && empty($data['risks'])) {
                            $data['risks'] = true;
                        }
                        if ($i == count($data['tree']) - 1 && !empty($data['risks'])) {
                            $section->addTextBreak();
                            $table = $section->addTable($this->borderTable);
                            $table->addRow(400, $this->tblHeader);
                            $table->addCell(Converter::cmToTwip(10.00), $this->restartAndBlackCell)
                                ->addText(
                                    $this->anrTranslate('Risk description'),
                                    $this->whiteFont,
                                    $this->centerParagraph
                                );
                            if ($this->anr->showRolfBrut == 1) {
                                $table->addCell(
                                    Converter::cmToTwip(5.50),
                                    $this->setColSpanCell(2 + count($opRisksImpactsScales), '444444')
                                )
                                    ->addText(
                                        $this->anrTranslate('Inherent risk'),
                                        $this->whiteFont,
                                        $this->centerParagraph
                                    );
                            }
                            $table->addCell(
                                Converter::cmToTwip(15.00),
                                $this->setColSpanCell(3 + count($opRisksImpactsScales), '444444')
                            )
                                ->addText(
                                    $this->anrTranslate('Net risk'),
                                    $this->whiteFont,
                                    $this->centerParagraph
                                );
                            $table->addCell(Converter::cmToTwip(2.00), $this->restartAndBlackCell)
                                ->addText(
                                    $this->anrTranslate('Treatment'),
                                    $this->whiteFont,
                                    $this->centerParagraph
                                );
                            $table->addCell(Converter::cmToTwip(2.00), $this->restartAndBlackCell)
                                ->addText(
                                    $this->anrTranslate('Residual risk'),
                                    $this->whiteFont,
                                    $this->centerParagraph
                                );

                            $table->addRow(400, $this->tblHeader);
                            $table->addCell(Converter::cmToTwip(10.00), $this->continueAndBlackCell);
                            if ($this->anr->showRolfBrut == 1) {
                                $table->addCell(Converter::cmToTwip(1.00), $this->restartAndBlackCell)
                                    ->addText(
                                        $this->anrTranslate('Prob.'),
                                        $this->whiteFont,
                                        $this->centerParagraph
                                    );
                                $table->addCell(
                                    Converter::cmToTwip($sizeCellImpact),
                                    $this->setColSpanCell(count($opRisksImpactsScales), '444444')
                                )
                                    ->addText(
                                        $this->anrTranslate('Impact'),
                                        $this->whiteFont,
                                        $this->centerParagraph
                                    );
                                $table->addCell(Converter::cmToTwip(1.00), $this->restartAndBlackCell)
                                    ->addText(
                                        $this->anrTranslate('Current risk'),
                                        $this->whiteFont,
                                        $this->centerParagraph
                                    );
                            }
                            $table->addCell(Converter::cmToTwip(1.00), $this->restartAndBlackCell)
                                ->addText(
                                    $this->anrTranslate('Prob.'),
                                    $this->whiteFont,
                                    $this->centerParagraph
                                );
                            $table->addCell(
                                Converter::cmToTwip($sizeCellImpact),
                                $this->setColSpanCell(count($opRisksImpactsScales), '444444')
                            )
                                ->addText(
                                    $this->anrTranslate('Impact'),
                                    $this->whiteFont,
                                    $this->centerParagraph
                                );
                            $table->addCell(Converter::cmToTwip(1.00), $this->restartAndBlackCell)
                                ->addText(
                                    $this->anrTranslate('Current risk'),
                                    $this->whiteFont,
                                    $this->centerParagraph
                                );
                            $table->addCell(Converter::cmToTwip(8.00), $this->restartAndBlackCell)
                                ->addText(
                                    $this->anrTranslate('Existing controls'),
                                    $this->whiteFont,
                                    $this->centerParagraph
                                );
                            $table->addCell(Converter::cmToTwip(2.00), $this->continueAndBlackCell);
                            $table->addCell(Converter::cmToTwip(2.00), $this->continueAndBlackCell);

                            $table->addRow(Converter::cmToTwip(1.00), $this->tblHeader);
                            $table->addCell(Converter::cmToTwip(10.00), $this->continueAndBlackCell);
                            if ($this->anr->showRolfBrut == 1) {
                                $table->addCell(Converter::cmToTwip(1.00), $this->continueAndBlackCell);
                                foreach ($opRisksImpactsScales as $opRiskImpactScale) {
                                    $label = mb_substr(_WT($opRiskImpactScale['label']), 0, 3) . '.';
                                    $table->addCell(
                                        Converter::cmToTwip(0.70),
                                        array_merge($this->rotate90TextCell, ['bgcolor' => '444444'])
                                    )
                                        ->addText(
                                            $label,
                                            $this->whiteFont,
                                            $this->verticalCenterParagraph
                                        );
                                }
                                $table->addCell(Converter::cmToTwip(1.00), $this->continueAndBlackCell);
                            }
                            $table->addCell(Converter::cmToTwip(1.00), $this->continueAndBlackCell);
                            foreach ($opRisksImpactsScales as $opRiskImpactScale) {
                                $label = mb_substr(_WT($opRiskImpactScale['label']), 0, 3) . '.';
                                $table->addCell(
                                    Converter::cmToTwip(0.70),
                                    array_merge($this->rotate90TextCell, ['bgcolor' => '444444'])
                                )
                                ->addText(
                                    $label,
                                    $this->whiteFont,
                                    $this->verticalCenterParagraph
                                );
                            }
                            $table->addCell(Converter::cmToTwip(1.00), $this->continueAndBlackCell);
                            $table->addCell(Converter::cmToTwip(8.00), $this->continueAndBlackCell);
                            $table->addCell(Converter::cmToTwip(2.00), $this->continueAndBlackCell);
                            $table->addCell(Converter::cmToTwip(2.00), $this->continueAndBlackCell);
                        }
                    }
                }

                if (!empty($data['risks']) && $data['risks'] !== true) {
                    $styleCell = $this->setColSpanCell(6 + count($opRisksImpactsScales), 'DFDFDF');
                    if ($this->anr->showRolfBrut == 1) {
                        $styleCell = $this->setColSpanCell(8 + count($opRisksImpactsScales) * 2, 'DFDFDF');
                    }
                    $table = $section->addTable($this->borderTable);
                    $table->addRow(400);
                    $table->addCell(Converter::cmToTwip(19.00), $styleCell)
                        ->addText(
                            _WT($data['path']),
                            $this->boldFont,
                            $this->leftParagraph
                        );
                    foreach ($data['risks'] as $r) {
                        if (!empty($data['risks'])) {
                            foreach ($r as $key => $value) {
                                if ($value == -1) {
                                    $r[$key] = '-';
                                }
                            }
                            $table->addRow(400);
                            $table->addCell(Converter::cmToTwip(10.00), $this->vAlignCenterCell)
                                ->addText(
                                    _WT($r['label']),
                                    $this->normalFont,
                                    $this->leftParagraph
                                );
                            if ($this->anr->showRolfBrut == 1) {
                                $table->addCell(Converter::cmToTwip(1.00), $this->vAlignCenterCell)
                                    ->addText(
                                        $r['brutProb'],
                                        $this->normalFont,
                                        $this->centerParagraph
                                    );
                                foreach ($opRisksImpactsScales as $opRiskImpactScale) {
                                    $table->addCell(Converter::cmToTwip(0.70), $this->vAlignCenterCell)
                                    ->addText(
                                        $r['scales'][$opRiskImpactScale['id']]['brutValue'],
                                        $this->normalFont,
                                        $this->centerParagraph
                                    );
                                }
                                $table->addCell(Converter::cmToTwip(1.00), $this->setBgColorCell($r['brutRisk'], false))
                                    ->addText(
                                        $r['brutRisk'],
                                        $this->boldFont,
                                        $this->centerParagraph
                                    );
                            }
                            $table->addCell(Converter::cmToTwip(1.00), $this->vAlignCenterCell)
                                ->addText(
                                    $r['netProb'],
                                    $this->normalFont,
                                    $this->centerParagraph
                                );
                            foreach ($opRisksImpactsScales as $opRiskImpactScale) {
                                $table->addCell(Converter::cmToTwip(0.70), $this->vAlignCenterCell)
                                ->addText(
                                    $r['scales'][$opRiskImpactScale['id']]['netValue'],
                                    $this->normalFont,
                                    $this->centerParagraph
                                );
                            }
                            $table->addCell(Converter::cmToTwip(1.00), $this->setBgColorCell($r['netRisk'], false))
                                ->addText(
                                    $r['netRisk'],
                                    $this->boldFont,
                                    $this->centerParagraph
                                );
                            $table->addCell(Converter::cmToTwip(8.00), $this->vAlignCenterCell)
                                ->addText(
                                    _WT($r['comment']),
                                    $this->normalFont,
                                    $this->leftParagraph
                                );
                            $table->addCell(Converter::cmToTwip(2.00), $this->vAlignCenterCell)
                                ->addText(
                                    $this->getKindfofMeasureLabel($r['kindOfMeasure']),
                                    $this->normalFont,
                                    $this->leftParagraph
                                );
                            $targetedRisk = $r['targetedRisk'] == '-' ? $r['netRisk'] : $r['targetedRisk'];
                            $table->addCell(Converter::cmToTwip(2.00), $this->setBgColorCell($targetedRisk, false))
                                ->addText(
                                    $targetedRisk,
                                    $this->boldFont,
                                    $this->centerParagraph
                                );
                        }
                    }
                }
            }
            return $this->getWordXmlFromWordObject($tableWord);
        }
    }

    /**
     * Generates Word-compliant HTML for the risks distribution paragraph
     * @return string HTML data that can be converted into WordXml data
     */
    protected function getRisksDistribution($infoRisk = true)
    {
        $this->cartoRiskService->buildListScalesAndHeaders($this->anr->getId());
        [$counters, $distrib] = $infoRisk ?
            $this->cartoRiskService->getCountersRisks('raw') :
            $this->cartoRiskService->getCountersOpRisks('raw') ;

        $colors = array(0, 1, 2);
        $sum = 0;

        foreach ($colors as $c) {
            if (!isset($distrib[$c])) {
                $distrib[$c] = 0;
            }
            $sum += $distrib[$c];
        }

        $intro = sprintf(
            $this->anrTranslate(
                "The list of risks addressed is provided as an attachment. It lists %d risk(s) of which:"
            ),
            $sum
        );

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
     * Generates the Risks by kind of treatment
     * @return mixed|string The WordXml data generated
     */
    protected function generateRisksByKindOfTreatment()
    {
        $result = null;
        $opRisksAllScales = $this->operationalRiskScaleService->getOperationalRiskScales($this->anr->getId());
        $opRisksImpactsScaleType = array_values(array_filter($opRisksAllScales, function ($scale) {
            return $scale['type'] == 1;
        }));
        $opRisksImpactsScales = array_filter($opRisksImpactsScaleType[0]['scaleTypes'], function ($scale) {
            return $scale['isHidden'] == false;
        });
        $sizeCellImpact = count($opRisksImpactsScales) * 0.70;

        for ($i = 1; $i <= 4; $i++) {
            $risksByTreatment = $this->get('anrInstanceRiskService')
                ->getInstanceRisks(
                    $this->anr->getId(),
                    null,
                    ['limit' => -1, 'order' => 'maxRisk', 'order_direction' => 'desc', 'kindOfMeasure' => $i]
                );
            $risksOpByTreatment = $this->get('anrInstanceRiskOpService')
                ->getOperationalRisks(
                    $this->anr,
                    null,
                    ['limit' => -1, 'order' => 'cacheNetRisk', 'order_direction' => 'desc', 'kindOfMeasure' => $i]
                );

            //create section
            $tableWord = new PhpWord();
            $section = $tableWord->addSection();
            $title = false;

            if (!empty($risksByTreatment)) {
                $title = true;
                $tableTitle = $section->addTable($this->noBorderTable);
                $tableTitle->addRow(400);
                $tableTitle->addCell(Converter::cmToTwip(10.00))
                ->addText(
                    $this->getKindfofMeasureLabel($i),
                    $this->titleFont,
                    $this->leftParagraph
                );
                $tableRiskInfo = $section->addTable($this->borderTable);

                $tableRiskInfo->addRow(400);
                $tableRiskInfo->addCell(Converter::cmToTwip(3.00), $this->restartAndGrayCell)
                    ->addText(
                        $this->anrTranslate('Asset'),
                        $this->boldFont,
                        $this->centerParagraph
                    );
                $tableRiskInfo->addCell(Converter::cmToTwip(2.10), $this->setColSpanCell(3, 'DFDFDF'))
                    ->addText(
                        $this->anrTranslate('Impact'),
                        $this->boldFont,
                        $this->centerParagraph
                    );
                $tableRiskInfo->addCell(Converter::cmToTwip(5.50), $this->setColSpanCell(2, 'DFDFDF'))
                    ->addText(
                        $this->anrTranslate('Threat'),
                        $this->boldFont,
                        $this->centerParagraph
                    );
                $tableRiskInfo->addCell(Converter::cmToTwip(10.00), $this->setColSpanCell(3, 'DFDFDF'))
                    ->addText(
                        $this->anrTranslate('Vulnerability'),
                        $this->boldFont,
                        $this->centerParagraph
                    );
                $tableRiskInfo->addCell(Converter::cmToTwip(3.00), $this->setColSpanCell(3, 'DFDFDF'))
                    ->addText(
                        $this->anrTranslate('Current risk'),
                        $this->boldFont,
                        $this->centerParagraph
                    );
                $tableRiskInfo->addCell(Converter::cmToTwip(2.00), $this->restartAndGrayCell)
                    ->addText(
                        $this->anrTranslate('Residual risk'),
                        $this->boldFont,
                        $this->centerParagraph
                    );
                $tableRiskInfo->addRow(400);
                $tableRiskInfo->addCell(Converter::cmToTwip(3.00), $this->continueAndGrayCell);
                $tableRiskInfo->addCell(Converter::cmToTwip(0.70), $this->grayCell)
                    ->addText(
                        'C',
                        $this->boldFont,
                        $this->centerParagraph
                    );
                $tableRiskInfo->addCell(Converter::cmToTwip(0.70), $this->grayCell)
                    ->addText(
                        'I',
                        $this->boldFont,
                        $this->centerParagraph
                    );
                $tableRiskInfo->addCell(Converter::cmToTwip(0.70), $this->grayCell)
                    ->addText(
                        $this->anrTranslate('A'),
                        $this->boldFont,
                        $this->centerParagraph
                    );
                $tableRiskInfo->addCell(Converter::cmToTwip(3.50), $this->grayCell)
                    ->addText(
                        $this->anrTranslate('Label'),
                        $this->boldFont,
                        $this->centerParagraph
                    );
                $tableRiskInfo->addCell(Converter::cmToTwip(2.00), $this->grayCell)
                    ->addText(
                        $this->anrTranslate('Prob.'),
                        $this->boldFont,
                        $this->centerParagraph
                    );
                $tableRiskInfo->addCell(Converter::cmToTwip(4.00), $this->grayCell)
                    ->addText(
                        $this->anrTranslate('Label'),
                        $this->boldFont,
                        $this->centerParagraph
                    );
                $tableRiskInfo->addCell(Converter::cmToTwip(4.00), $this->grayCell)
                    ->addText(
                        $this->anrTranslate('Existing controls'),
                        $this->boldFont,
                        $this->centerParagraph
                    );
                $tableRiskInfo->addCell(Converter::cmToTwip(2.00), $this->grayCell)
                    ->addText(
                        $this->anrTranslate('Qualif.'),
                        $this->boldFont,
                        $this->centerParagraph
                    );
                $tableRiskInfo->addCell(Converter::cmToTwip(1.00), $this->grayCell)
                    ->addText(
                        'C',
                        $this->boldFont,
                        $this->centerParagraph
                    );
                $tableRiskInfo->addCell(Converter::cmToTwip(1.00), $this->grayCell)
                    ->addText(
                        'I',
                        $this->boldFont,
                        $this->centerParagraph
                    );
                $tableRiskInfo->addCell(Converter::cmToTwip(1.00), $this->grayCell)
                    ->addText(
                        $this->anrTranslate('A'),
                        $this->boldFont,
                        $this->centerParagraph
                    );
                $tableRiskInfo->addCell(Converter::cmToTwip(2.00), $this->continueAndGrayCell);

                $impacts = ['c', 'i', 'd'];
                foreach ($risksByTreatment as $r) {
                    foreach ($impacts as $impact) {
                        if ($r[$impact . '_risk_enabled'] == 0) {
                            $r[$impact . '_risk'] = null;
                        }
                    }
                    foreach ($r as $key => $value) {
                        if ($value == -1) {
                            $r[$key] = '-';
                        }
                    }
                    $instance = $this->instanceTable->findById($r['instance']);
                    if (!$instance->getObject()->isScopeGlobal()) {
                        $path = $instance->getHierarchyString();
                    } else {
                        $path = $instance->getName($this->currentLangAnrIndex)
                            . ' (' . $this->anrTranslate('Global') . ')';
                    }

                    $tableRiskInfo->addRow(400);
                    $tableRiskInfo->addCell(Converter::cmToTwip(3.00), $this->vAlignCenterCell)
                        ->addText(
                            _WT($path),
                            $this->normalFont,
                            $this->leftParagraph
                        );
                    $tableRiskInfo->addCell(Converter::cmToTwip(0.70), $this->vAlignCenterCell)
                        ->addText(
                            $r['c_impact'],
                            $this->normalFont,
                            $this->centerParagraph
                        );
                    $tableRiskInfo->addCell(Converter::cmToTwip(0.70), $this->vAlignCenterCell)
                        ->addText(
                            $r['i_impact'],
                            $this->normalFont,
                            $this->centerParagraph
                        );
                    $tableRiskInfo->addCell(Converter::cmToTwip(0.70), $this->vAlignCenterCell)
                        ->addText(
                            $r['d_impact'],
                            $this->normalFont,
                            $this->centerParagraph
                        );
                    $tableRiskInfo->addCell(Converter::cmToTwip(3.50), $this->vAlignCenterCell)
                        ->addText(
                            _WT($r['threatLabel' . $this->currentLangAnrIndex]),
                            $this->normalFont,
                            $this->leftParagraph
                        );
                    $tableRiskInfo->addCell(Converter::cmToTwip(2.00), $this->vAlignCenterCell)
                        ->addText(
                            $r['threatRate'],
                            $this->normalFont,
                            $this->centerParagraph
                        );
                    $tableRiskInfo->addCell(Converter::cmToTwip(4.00), $this->vAlignCenterCell)
                        ->addText(
                            _WT($r['vulnLabel' . $this->currentLangAnrIndex]),
                            $this->normalFont,
                            $this->leftParagraph
                        );
                    $tableRiskInfo->addCell(Converter::cmToTwip(4.00), $this->vAlignCenterCell)
                        ->addText(
                            _WT($r['comment']),
                            $this->normalFont,
                            $this->leftParagraph
                        );
                    $tableRiskInfo->addCell(Converter::cmToTwip(2.00), $this->vAlignCenterCell)
                        ->addText(
                            $r['vulnerabilityRate'],
                            $this->normalFont,
                            $this->centerParagraph
                        );
                    $tableRiskInfo->addCell(Converter::cmToTwip(1.00), $this->setBgColorCell($r['c_risk']))
                        ->addText(
                            $r['c_risk'],
                            $this->boldFont,
                            $this->centerParagraph
                        );
                    $tableRiskInfo->addCell(Converter::cmToTwip(1.00), $this->setBgColorCell($r['i_risk']))
                        ->addText(
                            $r['i_risk'],
                            $this->boldFont,
                            $this->centerParagraph
                        );
                    $tableRiskInfo->addCell(Converter::cmToTwip(1.00), $this->setBgColorCell($r['d_risk']))
                        ->addText(
                            $r['d_risk'],
                            $this->boldFont,
                            $this->centerParagraph
                        );
                    $tableRiskInfo->addCell(Converter::cmToTwip(2.00), $this->setBgColorCell($r['target_risk']))
                        ->addText(
                            $r['target_risk'],
                            $this->boldFont,
                            $this->centerParagraph
                        );
                }
                $section->addTextBreak();
            }
            if (!empty($risksOpByTreatment)) {
                if (!$title) {
                    $tableTitle = $section->addTable($this->noBorderTable);
                    $tableTitle->addRow(400);
                    $tableTitle->addCell(Converter::cmToTwip(10.00), $this->setColSpanCell(13))
                        ->addText(
                            $this->getKindfofMeasureLabel($i),
                            $this->titleFont,
                            $this->leftParagraph
                        );
                }
                $tableRiskOp = $section->addTable($this->borderTable);

                $tableRiskOp->addRow(400);
                $tableRiskOp->addCell(Converter::cmToTwip(3.00), $this->restartAndGrayCell)
                    ->addText(
                        $this->anrTranslate('Asset'),
                        $this->boldFont,
                        $this->centerParagraph
                    );
                $tableRiskOp->addCell(Converter::cmToTwip(10.00), $this->restartAndGrayCell)
                    ->addText(
                        $this->anrTranslate('Risk description'),
                        $this->boldFont,
                        $this->centerParagraph
                    );
                if ($this->anr->showRolfBrut == 1) {
                    $tableRiskOp
                        ->addCell(
                            Converter::cmToTwip(5.50),
                            $this->setColSpanCell(2 + count($opRisksImpactsScales), 'DFDFDF')
                        )
                        ->addText(
                            $this->anrTranslate('Inherent risk'),
                            $this->boldFont,
                            $this->centerParagraph
                        );
                }
                $tableRiskOp
                    ->addCell(
                        Converter::cmToTwip(15.00),
                        $this->setColSpanCell(3 + count($opRisksImpactsScales), 'DFDFDF')
                    )
                    ->addText(
                        $this->anrTranslate('Net risk'),
                        $this->boldFont,
                        $this->centerParagraph
                    );
                $tableRiskOp->addCell(Converter::cmToTwip(2.00), $this->restartAndGrayCell)
                    ->addText(
                        $this->anrTranslate('Residual risk'),
                        $this->boldFont,
                        $this->centerParagraph
                    );

                $tableRiskOp->addRow(400, $this->tblHeader);
                $tableRiskOp->addCell(Converter::cmToTwip(3.00), $this->continueAndGrayCell);
                $tableRiskOp->addCell(Converter::cmToTwip(10.00), $this->continueAndGrayCell);
                if ($this->anr->showRolfBrut == 1) {
                    $tableRiskOp->addCell(Converter::cmToTwip(1.00), $this->restartAndGrayCell)
                        ->addText(
                            $this->anrTranslate('Prob.'),
                            $this->boldFont,
                            $this->centerParagraph
                        );
                    $tableRiskOp
                        ->addCell(
                            Converter::cmToTwip($sizeCellImpact),
                            $this->setColSpanCell(count($opRisksImpactsScales), 'DFDFDF')
                        )
                        ->addText(
                            $this->anrTranslate('Impact'),
                            $this->boldFont,
                            $this->centerParagraph
                        );
                    $tableRiskOp->addCell(Converter::cmToTwip(1.00), $this->restartAndGrayCell)
                        ->addText(
                            $this->anrTranslate('Current risk'),
                            $this->boldFont,
                            $this->centerParagraph
                        );
                }
                $tableRiskOp->addCell(Converter::cmToTwip(1.00), $this->restartAndGrayCell)
                    ->addText(
                        $this->anrTranslate('Prob.'),
                        $this->boldFont,
                        $this->centerParagraph
                    );
                $tableRiskOp
                    ->addCell(
                        Converter::cmToTwip($sizeCellImpact),
                        $this->setColSpanCell(count($opRisksImpactsScales), 'DFDFDF')
                    )
                    ->addText(
                        $this->anrTranslate('Impact'),
                        $this->boldFont,
                        $this->centerParagraph
                    );
                $tableRiskOp->addCell(Converter::cmToTwip(1.00), $this->restartAndGrayCell)
                    ->addText(
                        $this->anrTranslate('Current risk'),
                        $this->boldFont,
                        $this->centerParagraph
                    );
                $tableRiskOp->addCell(Converter::cmToTwip(8.00), $this->restartAndGrayCell)
                    ->addText(
                        $this->anrTranslate('Existing controls'),
                        $this->boldFont,
                        $this->centerParagraph
                    );
                $tableRiskOp->addCell(Converter::cmToTwip(2.00), $this->continueAndGrayCell);

                $tableRiskOp->addRow(Converter::cmToTwip(1.00));
                $tableRiskOp->addCell(Converter::cmToTwip(3.00), $this->continueAndGrayCell);
                $tableRiskOp->addCell(Converter::cmToTwip(10.00), $this->continueAndGrayCell);
                if ($this->anr->showRolfBrut == 1) {
                    $tableRiskOp->addCell(Converter::cmToTwip(1.00), $this->continueAndGrayCell);
                    foreach ($opRisksImpactsScales as $opRiskImpactScale) {
                        $label = mb_substr(_WT($opRiskImpactScale['label']), 0, 3) . '.';
                        $tableRiskOp
                            ->addCell(
                                Converter::cmToTwip(0.70),
                                array_merge($this->rotate90TextCell, ['bgcolor' => 'DFDFDF'])
                            )
                            ->addText(
                                $label,
                                $this->boldFont,
                                $this->verticalCenterParagraph
                            );
                    }
                    $tableRiskOp->addCell(Converter::cmToTwip(1.00), $this->continueAndGrayCell);
                }
                $tableRiskOp->addCell(Converter::cmToTwip(1.00), $this->continueAndGrayCell);
                foreach ($opRisksImpactsScales as $opRiskImpactScale) {
                    $label = mb_substr(_WT($opRiskImpactScale['label']), 0, 3) . '.';
                    $tableRiskOp
                        ->addCell(
                            Converter::cmToTwip(0.70),
                            array_merge($this->rotate90TextCell, ['bgcolor' => 'DFDFDF'])
                        )
                        ->addText(
                            $label,
                            $this->boldFont,
                            $this->verticalCenterParagraph
                        );
                }
                $tableRiskOp->addCell(Converter::cmToTwip(1.00), $this->continueAndGrayCell);
                $tableRiskOp->addCell(Converter::cmToTwip(8.00), $this->continueAndGrayCell);
                $tableRiskOp->addCell(Converter::cmToTwip(2.00), $this->continueAndGrayCell);

                foreach ($risksOpByTreatment as $r) {
                    $instanceRiskOp = $this->get('instanceRiskOpTable')->findById($r['id']);
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
                        if ($value == -1) {
                            $r[$key] = '-';
                        }
                    }


                    $instance = $this->instanceTable->findById($r['instanceInfos']['id']);
                    $path = $instance->getHierarchyString();

                    $tableRiskOp->addRow(400);
                    $tableRiskOp->addCell(Converter::cmToTwip(3.00), $this->vAlignCenterCell)
                        ->addText(
                            _WT($path),
                            $this->normalFont,
                            $this->leftParagraph
                        );
                    $tableRiskOp->addCell(Converter::cmToTwip(10.00), $this->vAlignCenterCell)
                        ->addText(
                            _WT($r['label' . $this->currentLangAnrIndex]),
                            $this->normalFont,
                            $this->leftParagraph
                        );
                    if ($this->anr->showRolfBrut == 1) {
                        $tableRiskOp->addCell(Converter::cmToTwip(1.00), $this->vAlignCenterCell)
                            ->addText(
                                $r['brutProb'],
                                $this->normalFont,
                                $this->centerParagraph
                            );
                        foreach ($opRisksImpactsScales as $opRiskImpactScale) {
                            $tableRiskOp->addCell(Converter::cmToTwip(0.70), $this->vAlignCenterCell)
                                ->addText(
                                    $r['scales'][$opRiskImpactScale['id']]['brutValue'],
                                    $this->normalFont,
                                    $this->centerParagraph
                                );
                        }
                        $tableRiskOp
                            ->addCell(
                                Converter::cmToTwip(1.00),
                                $this->setBgColorCell($r['cacheBrutRisk'], false)
                            )
                            ->addText(
                                $r['cacheBrutRisk'],
                                $this->boldFont,
                                $this->centerParagraph
                            );
                    }
                    $tableRiskOp->addCell(Converter::cmToTwip(1.00), $this->vAlignCenterCell)
                        ->addText(
                            $r['netProb'],
                            $this->normalFont,
                            $this->centerParagraph
                        );
                    foreach ($opRisksImpactsScales as $opRiskImpactScale) {
                        $tableRiskOp->addCell(Converter::cmToTwip(0.70), $this->vAlignCenterCell)
                            ->addText(
                                $r['scales'][$opRiskImpactScale['id']]['netValue'],
                                $this->normalFont,
                                $this->centerParagraph
                            );
                    }
                    $tableRiskOp->addCell(Converter::cmToTwip(1.00), $this->setBgColorCell($r['cacheNetRisk'], false))
                        ->addText(
                            $r['cacheNetRisk'],
                            $this->boldFont,
                            $this->centerParagraph
                        );
                    $tableRiskOp->addCell(Converter::cmToTwip(8.00), $this->vAlignCenterCell)
                        ->addText(
                            _WT($r['comment']),
                            $this->normalFont,
                            $this->leftParagraph
                        );
                    $cacheTargetedRisk = $r['cacheTargetedRisk'] == '-' ? $r['cacheNetRisk'] : $r['cacheTargetedRisk'];
                    $tableRiskOp->addCell(Converter::cmToTwip(2.00), $this->setBgColorCell($cacheTargetedRisk, false))
                        ->addText(
                            $cacheTargetedRisk,
                            $this->boldFont,
                            $this->centerParagraph
                        );
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
    protected function generateRisksPlan()
    {
        $recommendationRisks = $this->recommendationRiskTable->findByAnr($this->anr, ['r.position' => 'ASC']);

        $tableWord = new PhpWord();
        $section = $tableWord->addSection();
        $table = $section->addTable($this->borderTable);

        if (!empty($recommendationRisks)) {
            $table->addRow(400, $this->tblHeader);
            $table->addCell(Converter::cmToTwip(3.50), $this->restartAndGrayCell)
                ->addText(
                    $this->anrTranslate('Asset'),
                    $this->boldFont,
                    $this->centerParagraph
                );
            $table->addCell(Converter::cmToTwip(6.00), $this->restartAndGrayCell)
                ->addText(
                    $this->anrTranslate('Threat'),
                    $this->boldFont,
                    $this->centerParagraph
                );
            $table->addCell(Converter::cmToTwip(6.00), $this->restartAndGrayCell)
                ->addText(
                    $this->anrTranslate('Vulnerability'),
                    $this->boldFont,
                    $this->centerParagraph
                );
            $table->addCell(Converter::cmToTwip(6.00), $this->restartAndGrayCell)
                ->addText(
                    $this->anrTranslate('Existing controls'),
                    $this->boldFont,
                    $this->centerParagraph
                );
            $table->addCell(Converter::cmToTwip(2.10), $this->setColSpanCell(3, 'DFDFDF'))
                ->addText(
                    $this->anrTranslate('Current risk'),
                    $this->boldFont,
                    $this->centerParagraph
                );
            $table->addCell(Converter::cmToTwip(2.10), $this->restartAndGrayCell)
                ->addText(
                    $this->anrTranslate('Treatment'),
                    $this->boldFont,
                    $this->centerParagraph
                );
            $table->addCell(Converter::cmToTwip(2.10), $this->restartAndGrayCell)
                ->addText(
                    $this->anrTranslate('Residual risk'),
                    $this->boldFont,
                    $this->centerParagraph
                );

            $table->addRow();
            $table->addCell(Converter::cmToTwip(3.00), $this->continueAndGrayCell);
            $table->addCell(Converter::cmToTwip(6.00), $this->continueAndGrayCell);
            $table->addCell(Converter::cmToTwip(6.00), $this->continueAndGrayCell);
            $table->addCell(Converter::cmToTwip(6.00), $this->continueAndGrayCell);
            $table->addCell(Converter::cmToTwip(0.70), $this->grayCell)
                ->addText(
                    'C',
                    $this->boldFont,
                    $this->centerParagraph
                );
            $table->addCell(Converter::cmToTwip(0.70), $this->grayCell)
                ->addText(
                    'I',
                    $this->boldFont,
                    $this->centerParagraph
                );
            $table->addCell(Converter::cmToTwip(0.70), $this->grayCell)
                ->addText(
                    $this->anrTranslate('A'),
                    $this->boldFont,
                    $this->centerParagraph
                );
            $table->addCell(Converter::cmToTwip(2.10), $this->continueAndGrayCell);
            $table->addCell(Converter::cmToTwip(2.10), $this->continueAndGrayCell);
        }

        $previousRecoId = null;
        $impacts = ['c', 'i', 'd'];

        //unset
        $global = [];
        $toUnset = [];
        foreach ($recommendationRisks as $recommendationRisk) {
            if ($recommendationRisk->hasGlobalObjectRelation()) {
                $key = $recommendationRisk->getRecommandation()->getUuid()
                    . ' - ' . $recommendationRisk->getThreat()->getUuid()
                    . ' - ' . $recommendationRisk->getVulnerability()->getUuid()
                    . ' - ' . $recommendationRisk->getGlobalObject()->getUuid();
                if (\array_key_exists($key, $global)) {
                    if (\array_key_exists($key, $toUnset)
                        && $recommendationRisk->getInstanceRisk()->getCacheMaxRisk() > $toUnset[$key]
                    ) {
                        $toUnset[$key] = $recommendationRisk->getInstanceRisk()->getCacheMaxRisk();
                    } else {
                        $toUnset[$key] = max($recommendationRisk->getInstanceRisk()->getCacheMaxRisk(), $global[$key]);
                    }
                }
                $global[$key] = $recommendationRisk->getInstanceRisk()->getCacheMaxRisk();
            }
        }

        $alreadySet = [];
        foreach ($recommendationRisks as $recommendationRisk) {
            if ($recommendationRisk->getInstanceRisk()) {
                foreach ($impacts as $impact) {
                    $risk = 'risk' . ucfirst($impact);
                    if ($impact == 'd') {
                        $impact = 'a'; // Changed to get threat->a value;
                    }
                    ${'risk' . ucfirst($impact)} = $recommendationRisk->getInstanceRisk()->$risk;

                    if ($recommendationRisk->getInstanceRisk()->$risk == -1) {
                        ${'risk' . ucfirst($impact)} = '-';
                    }

                    if (!$recommendationRisk->getThreat()->$impact) {
                        ${'risk' . ucfirst($impact)} = null;
                    }
                }

                $importance = '';
                for ($i = 0; $i <= ($recommendationRisk->getRecommandation()->getImportance() - 1); $i++) {
                    $importance .= '●';
                }

                if ($recommendationRisk->getRecommandation()->getUuid() !== $previousRecoId) {
                    $table->addRow(400);
                    $cellReco = $table->addCell(Converter::cmToTwip(5.00), $this->setColSpanCell(9, 'DBE5F1'));
                    $cellRecoRun = $cellReco->addTextRun($this->leftParagraph);
                    $cellRecoRun->addText(
                        $importance . ' ',
                        $this->redFont
                    );
                    $cellRecoRun->addText(
                        _WT($recommendationRisk->getRecommandation()->getCode()),
                        $this->boldFont
                    );
                    $cellRecoRun->addText(
                        ' - ' . _WT($recommendationRisk->getRecommandation()->getDescription()),
                        $this->boldFont
                    );
                }

                $continue = true;

                $key = $recommendationRisk->getRecommandation()->getUuid()
                    . ' - ' . $recommendationRisk->getThreat()->getUuid()
                    . ' - ' . $recommendationRisk->getVulnerability()->getUuid()
                    . ' - ' . (
                        $recommendationRisk->hasGlobalObjectRelation()
                            ? $recommendationRisk->getGlobalObject()->getUuid()
                            : ''
                    );
                if (isset($toUnset[$key])) {
                    if ($recommendationRisk->getInstanceRisk()->getCacheMaxRisk() < $toUnset[$key]
                        || isset($alreadySet[$key])
                    ) {
                        $continue = false;
                    } else {
                        $alreadySet[$key] = true;
                    }
                }

                if ($continue) {
                    $path = $this->getObjectInstancePath($recommendationRisk);

                    $table->addRow(400);
                    $table->addCell(Converter::cmToTwip(3.00), $this->vAlignCenterCell)
                        ->addText(
                            _WT($path),
                            $this->normalFont,
                            $this->leftParagraph
                        );
                    $table->addCell(Converter::cmToTwip(6.00), $this->vAlignCenterCell)
                        ->addText(
                            _WT($recommendationRisk->getThreat()->{'label' . $this->currentLangAnrIndex}),
                            $this->normalFont,
                            $this->leftParagraph
                        );
                    $table->addCell(Converter::cmToTwip(6.00), $this->vAlignCenterCell)
                        ->addText(
                            _WT($recommendationRisk->getVulnerability()->{'label' . $this->currentLangAnrIndex}),
                            $this->normalFont,
                            $this->leftParagraph
                        );
                    $table->addCell(Converter::cmToTwip(6.00), $this->vAlignCenterCell)
                        ->addText(
                            _WT($recommendationRisk->getInstanceRisk()->getComment()),
                            $this->normalFont,
                            $this->leftParagraph
                        );
                    $table->addCell(Converter::cmToTwip(0.70), $this->setBgColorCell($riskC))
                        ->addText(
                            $riskC,
                            $this->boldFont,
                            $this->centerParagraph
                        );
                    $table->addCell(Converter::cmToTwip(0.70), $this->setBgColorCell($riskI))
                        ->addText(
                            $riskI,
                            $this->boldFont,
                            $this->centerParagraph
                        );
                    $table->addCell(Converter::cmToTwip(0.70), $this->setBgColorCell($riskA))
                        ->addText(
                            $riskA,
                            $this->boldFont,
                            $this->centerParagraph
                        );
                    $table->addCell(Converter::cmToTwip(2.10), $this->vAlignCenterCell)
                        ->addText(
                            $this->anrTranslate($recommendationRisk->getInstanceRisk()->getTreatmentName()),
                            $this->normalFont,
                            $this->leftParagraph
                        );
                    $table
                        ->addCell(
                            Converter::cmToTwip(2.10),
                            $this->setBgColorCell($recommendationRisk->getInstanceRisk()->getCacheTargetedRisk())
                        )
                        ->addText(
                            $recommendationRisk->getInstanceRisk()->getCacheTargetedRisk(),
                            $this->boldFont,
                            $this->centerParagraph
                        );
                }
            }
            $previousRecoId = $recommendationRisk->getRecommandation()->getUuid();
        }

        return $table;
    }

    /**
     * Generates the Operational Risks Plan data
     * @return mixed|string The WordXml data generated
     */
    protected function generateOperationalRisksPlan()
    {
        $recommendationRisks = $this->recommendationRiskTable->findByAnr($this->anr, ['r.position' => 'ASC']);

        $tableWord = new PhpWord();
        $section = $tableWord->addSection();
        $table = $section->addTable($this->borderTable);

        if (!empty($recommendationRisks)) {
            $table->addRow(400, $this->tblHeader);
            $table->addCell(Converter::cmToTwip(3.00), $this->grayCell)
                ->addText(
                    $this->anrTranslate('Asset'),
                    $this->boldFont,
                    $this->centerParagraph
                );
            $table->addCell(Converter::cmToTwip(12.20), $this->grayCell)
                ->addText(
                    $this->anrTranslate('Risk description'),
                    $this->boldFont,
                    $this->centerParagraph
                );
            $table->addCell(Converter::cmToTwip(6.00), $this->grayCell)
                ->addText(
                    $this->anrTranslate('Existing controls'),
                    $this->boldFont,
                    $this->centerParagraph
                );
            $table->addCell(Converter::cmToTwip(2.10), $this->grayCell)
                ->addText(
                    $this->anrTranslate('Current risk'),
                    $this->boldFont,
                    $this->centerParagraph
                );
            $table->addCell(Converter::cmToTwip(2.10), $this->grayCell)
                ->addText(
                    $this->anrTranslate('Treatment'),
                    $this->boldFont,
                    $this->centerParagraph
                );
            $table->addCell(Converter::cmToTwip(2.10), $this->grayCell)
                ->addText(
                    $this->anrTranslate('Residual risk'),
                    $this->boldFont,
                    $this->centerParagraph
                );
        }

        $previousRecoId = null;
        foreach ($recommendationRisks as $recommendationRisk) {
            if ($recommendationRisk->getInstanceRiskOp()) {
                $cacheNetRisk = $recommendationRisk->getInstanceRiskOp()->getCacheNetRisk() !== -1 ?
                    $recommendationRisk->getInstanceRiskOp()->getCacheNetRisk() :
                    '-';
                $cacheTargetedRisk = $recommendationRisk->getInstanceRiskOp()->getCacheTargetedRisk() !== -1 ?
                    $recommendationRisk->getInstanceRiskOp()->getCacheTargetedRisk() :
                    $cacheNetRisk;

                $importance = '';
                for ($i = 0; $i <= ($recommendationRisk->getRecommandation()->getImportance() - 1); $i++) {
                    $importance .= '●';
                }

                if ($recommendationRisk->getRecommandation()->getUuid() !== $previousRecoId) {
                    $table->addRow(400);
                    $cellReco = $table->addCell(Converter::cmToTwip(5.00), $this->setColSpanCell(6, 'DBE5F1'));
                    $cellRecoRun = $cellReco->addTextRun($this->leftParagraph);
                    $cellRecoRun->addText(
                        $importance . ' ',
                        $this->redFont
                    );
                    $cellRecoRun->addText(
                        _WT($recommendationRisk->getRecommandation()->getCode()),
                        $this->boldFont
                    );
                    $cellRecoRun->addText(
                        ' - ' . _WT($recommendationRisk->getRecommandation()->getDescription()),
                        $this->boldFont
                    );
                }

                $path = $this->getObjectInstancePath($recommendationRisk);

                $table->addRow(400);
                $table->addCell(Converter::cmToTwip(3.00), $this->vAlignCenterCell)
                    ->addText(
                        _WT($path),
                        $this->normalFont,
                        $this->leftParagraph
                    );
                $table->addCell(Converter::cmToTwip(12.20), $this->vAlignCenterCell)
                    ->addText(
                        _WT($recommendationRisk->getInstanceRiskOp()->{'riskCacheLabel' . $this->currentLangAnrIndex}),
                        $this->normalFont,
                        $this->leftParagraph
                    );
                $table->addCell(Converter::cmToTwip(6.00), $this->vAlignCenterCell)
                    ->addText(
                        _WT($recommendationRisk->getInstanceRiskOp()->getComment()),
                        $this->normalFont,
                        $this->leftParagraph
                    );
                $table->addCell(Converter::cmToTwip(2.10), $this->setBgColorCell($cacheNetRisk, false))
                    ->addText(
                        $cacheNetRisk,
                        $this->boldFont,
                        $this->centerParagraph
                    );
                $table->addCell(Converter::cmToTwip(2.10), $this->vAlignCenterCell)
                    ->addText(
                        $this->anrTranslate($recommendationRisk->getInstanceRiskOp()->getTreatmentName()),
                        $this->normalFont,
                        $this->leftParagraph
                    );

                $table->addCell(Converter::cmToTwip(2.10), $this->setBgColorCell($cacheTargetedRisk, false))
                    ->addText(
                        $cacheTargetedRisk,
                        $this->boldFont,
                        $this->centerParagraph
                    );

                $previousRecoId = $recommendationRisk->getRecommandation()->getUuid();
            }
        }

        return $table;
    }

    /**
     * Generates the Implamentation Recommendations Plan data
     * @return mixed|string The WordXml data generated
     */
    protected function generateTableImplementationPlan()
    {
        $recommendationRisks = $this->recommendationRiskTable->findByAnr($this->anr, ['r.position' => 'ASC']);

        $tableWord = new PhpWord();
        $section = $tableWord->addSection();
        $table = $section->addTable($this->borderTable);

        if (!empty($recommendationRisks)) {
            $table->addRow(400, $this->tblHeader);
            $table->addCell(Converter::cmToTwip(10.00), $this->grayCell)
                ->addText(
                    $this->anrTranslate('Recommendation'),
                    $this->boldFont,
                    $this->centerParagraph
                );
            $table->addCell(Converter::cmToTwip(2.00), $this->grayCell)
                ->addText(
                    $this->anrTranslate('Imp.'),
                    $this->boldFont,
                    $this->centerParagraph
                );
            $table->addCell(Converter::cmToTwip(5.00), $this->grayCell)
                ->addText(
                    $this->anrTranslate('Comment'),
                    $this->boldFont,
                    $this->centerParagraph
                );
            $table->addCell(Converter::cmToTwip(4.00), $this->grayCell)
                ->addText(
                    $this->anrTranslate('Manager'),
                    $this->boldFont,
                    $this->centerParagraph
                );
            $table->addCell(Converter::cmToTwip(3.00), $this->grayCell)
                ->addText(
                    $this->anrTranslate('Deadline'),
                    $this->boldFont,
                    $this->centerParagraph
                );
        }

        foreach ($recommendationRisks as $recommendationRisk) {
            $recommendation = $recommendationRisk->getRecommandation();
            $importance = '';
            for ($i = 0; $i <= ($recommendation->getImportance() - 1); $i++) {
                $importance .= '●';
            }

            $recoDeadline = '';
            if ($recommendation->getDueDate()) {
                $recoDeadline = $recommendation->getDueDate()->format('d-m-Y');
            }

            $table->addRow(400);
            $cellRecoName = $table->addCell(Converter::cmToTwip(5.00), $this->vAlignCenterCell);
            $cellRecoNameRun = $cellRecoName->addTextRun($this->leftParagraph);
            $cellRecoNameRun->addText(
                _WT($recommendation->getCode()) . '<w:br/>',
                $this->boldFont
            );
            $cellRecoNameRun->addText(
                _WT($recommendation->getDescription()),
                $this->normalFont
            );
            $table->addCell(Converter::cmToTwip(2.00), $this->vAlignCenterCell)
                ->addText(
                    $importance,
                    $this->redFont,
                    $this->centerParagraph
                );
            $table->addCell(Converter::cmToTwip(5.00), $this->vAlignCenterCell)
                ->addText(
                    _WT($recommendation->getComment()),
                    $this->normalFont,
                    $this->leftParagraph
                );
            $table->addCell(Converter::cmToTwip(4.00), $this->vAlignCenterCell)
                ->addText(
                    _WT($recommendation->getResponsable()),
                    $this->normalFont,
                    $this->centerParagraph
                );
            $table->addCell(Converter::cmToTwip(3.00), $this->vAlignCenterCell)
                ->addText(
                    $recoDeadline,
                    $this->normalFont,
                    $this->centerParagraph
                );
        }

        return $table;
    }

    /**
     * Generates the Implamentation Recommendations Plan data
     * @return mixed|string The WordXml data generated
     */
    protected function generateTableImplementationHistory()
    {
        $recoRecords = $this->recommendationHistoricTable->findByAnr($this->anr);

        $tableWord = new PhpWord();
        $section = $tableWord->addSection();
        $table = $section->addTable($this->borderTable);

        if ($recoRecords) {
            $table->addRow(400, $this->tblHeader);
            $table->addCell(Converter::cmToTwip(3.00), $this->grayCell)
                ->addText(
                    $this->anrTranslate('By'),
                    $this->boldFont,
                    $this->centerParagraph
                );
            $table->addCell(Converter::cmToTwip(6.00), $this->grayCell)
                ->addText(
                    $this->anrTranslate('Recommendation'),
                    $this->boldFont,
                    $this->centerParagraph
                );
            $table->addCell(Converter::cmToTwip(8.00), $this->grayCell)
                ->addText(
                    $this->anrTranslate('Risk'),
                    $this->boldFont,
                    $this->centerParagraph
                );
            $table->addCell(Converter::cmToTwip(4.50), $this->grayCell)
                ->addText(
                    $this->anrTranslate('Implementation comment'),
                    $this->boldFont,
                    $this->centerParagraph
                );
            $table->addCell(Converter::cmToTwip(1.75), $this->grayCell)
                ->addText(
                    $this->anrTranslate('Risk before'),
                    $this->boldFont,
                    $this->centerParagraph
                );
            $table->addCell(Converter::cmToTwip(1.75), $this->grayCell)
                ->addText(
                    $this->anrTranslate('Risk after'),
                    $this->boldFont,
                    $this->centerParagraph
                );
        }

        $previousRecoId = null;

        foreach ($recoRecords as $recoRecord) {
            $importance = '';
            for ($i = 0; $i <= ($recoRecord->recoImportance - 1); $i++) {
                $importance .= '●';
            }

            if ($recoRecord->recoDuedate == null) {
                $recoDeadline = '';
            } else {
                $recoDeadline = $recoRecord->recoDuedate->format('d/m/Y');
            }

            $recoValidationDate = $recoRecord->createdAt->format('d/m/Y');

            if ($recoRecord->riskColorBefore == "green") {
                $bgcolorRiskBefore = 'D6F107';
            } else {
                if ($recoRecord->riskColorBefore == "orange") {
                    $bgcolorRiskBefore = 'FFBC1C';
                } else {
                    if ($recoRecord->riskMaxRiskBefore == -1) {
                        $recoRecord->riskMaxRiskBefore = '-';
                        $bgcolorRiskBefore = 'FFFFFF';
                    } else {
                        $bgcolorRiskBefore = 'FD661F';
                    }
                }
            }

            $styleContentCellRiskBefore = ['valign' => 'center', 'bgcolor' => $bgcolorRiskBefore];

            if ($recoRecord->riskColorAfter == "green") {
                $bgcolorRiskAfter = 'D6F107';
            } else {
                if ($recoRecord->riskColorAfter == "orange") {
                    $bgcolorRiskAfter = 'FFBC1C';
                } else {
                    if ($recoRecord->riskMaxRiskAfter == -1) {
                        $recoRecord->riskMaxRiskAfter = '-';
                        $bgcolorRiskAfter = 'FFFFFF';
                    } else {
                        $bgcolorRiskBefore = 'FD661F';
                    }
                }
            }

            $styleContentCellRiskAfter = ['valign' => 'center', 'bgcolor' => $bgcolorRiskAfter];

            $table->addRow(400);
            $table->addCell(Converter::cmToTwip(3.00), $this->vAlignCenterCell)
                ->addText(
                    _WT($recoRecord->creator),
                    $this->normalFont,
                    $this->leftParagraph
                );
            $cellReco = $table->addCell(Converter::cmToTwip(6.00), $this->vAlignCenterCell);
            $cellRecoRun = $cellReco->addTextRun($this->leftParagraph);
            $cellRecoRun->addText(
                $importance . ' ',
                $this->redFont
            );
            $cellRecoRun->addText(
                _WT($recoRecord->recoCode) . '<w:br/>',
                $this->boldFont
            );
            $cellRecoRun->addText(
                _WT($recoRecord->recoDescription) . '<w:br/>' . '<w:br/>',
                $this->normalFont
            );
            $cellRecoRun->addText(
                $this->anrTranslate('Comment') . ': ',
                $this->boldFont
            );
            $cellRecoRun->addText(
                _WT($recoRecord->recoComment) . '<w:br/>',
                $this->normalFont
            );
            $cellRecoRun->addText(
                $this->anrTranslate('Deadline') . ': ',
                $this->boldFont
            );
            $cellRecoRun->addText(
                $recoDeadline . '<w:br/>',
                $this->normalFont
            );
            $cellRecoRun->addText(
                $this->anrTranslate('Validation date') . ': ',
                $this->boldFont
            );
            $cellRecoRun->addText(
                $recoValidationDate . '<w:br/>',
                $this->normalFont
            );
            $cellRecoRun->addText(
                $this->anrTranslate('Manager') . ': ',
                $this->boldFont
            );
            $cellRecoRun->addText(
                _WT($recoRecord->recoResponsable),
                $this->normalFont
            );
            $cellRisk = $table->addCell(Converter::cmToTwip(8.00), $this->vAlignCenterCell);
            $cellRiskRun = $cellRisk->addTextRun($this->leftParagraph);
            $cellRiskRun->addText(
                $this->anrTranslate('Asset type') . ': ',
                $this->boldFont
            );
            $cellRiskRun->addText(
                _WT($recoRecord->riskAsset) . '<w:br/>',
                $this->normalFont
            );
            $cellRiskRun->addText(
                $this->anrTranslate('Asset') . ': ',
                $this->boldFont
            );
            $cellRiskRun->addText(
                _WT($recoRecord->riskInstance) . '<w:br/>',
                $this->normalFont
            );
            $cellRiskRun->addText(
                $this->anrTranslate('Threat') . ': ',
                $this->boldFont
            );
            $cellRiskRun->addText(
                _WT($recoRecord->riskThreat) . '<w:br/>',
                $this->normalFont
            );
            $cellRiskRun->addText(
                $this->anrTranslate('Vulnerability') . ': ',
                $this->boldFont
            );
            $cellRiskRun->addText(
                _WT($recoRecord->riskVul) . '<w:br/>',
                $this->normalFont
            );
            $cellRiskRun->addText(
                $this->anrTranslate('Treatment type') . ': ',
                $this->boldFont
            );
            $cellRiskRun->addText(
                $this->getKindfofMeasureLabel($recoRecord->riskKindOfMeasure) . '<w:br/>',
                $this->normalFont
            );
            $cellRiskRun->addText(
                $this->anrTranslate('Existing controls') . ': ',
                $this->boldFont
            );
            $cellRiskRun->addText(
                _WT($recoRecord->riskCommentBefore) . '<w:br/>',
                $this->normalFont
            );
            $cellRiskRun->addText(
                $this->anrTranslate('New controls') . ': ',
                $this->boldFont
            );
            $cellRiskRun->addText(
                _WT($recoRecord->riskCommentAfter) . '<w:br/>',
                $this->normalFont
            );
            $table->addCell(Converter::cmToTwip(4.50), $this->vAlignCenterCell)
                ->addText(
                    _WT($recoRecord->implComment),
                    $this->normalFont,
                    $this->leftParagraph
                );
            $table->addCell(Converter::cmToTwip(1.75), $styleContentCellRiskBefore)
                ->addText(
                    $recoRecord->riskMaxRiskBefore,
                    $this->boldFont,
                    $this->centerParagraph
                );
            $table->addCell(Converter::cmToTwip(1.75), $styleContentCellRiskAfter)
                ->addText(
                    $recoRecord->riskMaxRiskAfter,
                    $this->boldFont,
                    $this->centerParagraph
                );

            $previousRecoRecordId = $recoRecord->id;
        }

        return $table;
    }

    /**
     * Generates the Statement Of Applicability Scale
     * @return mixed|string The WordXml data generated
     */
    protected function generateTableStatementOfApplicabilityScale($soaScaleComments, $translations)
    {
        $tableWord = new PhpWord();
        $section = $tableWord->addSection();
        $table = $section->addTable($this->borderTable);
        $noBorderCell = [
            'borderTopColor' => 'FFFFFF',
            'borderTopSize' => 0,
            'borderLeftColor' =>  'FFFFFF',
            'borderLeftSize' => 0,
        ];

        if (!empty($soaScaleComments)) {
            $table->addRow(400, $this->tblHeader);
            $table->addCell(Converter::cmToTwip(2.00), $noBorderCell);
            $table->addCell(Converter::cmToTwip(8.00), $this->grayCell)
                ->addText(
                    $this->anrTranslate('Level of compliance'),
                    $this->boldFont,
                    $this->centerParagraph
                );

            foreach ($soaScaleComments as $comment) {
                $table->addRow(400);
                $translationComment = $translations[$comment->getCommentTranslationKey()] ?? null;

                $table->addCell(Converter::cmToTwip(2.00), $this->vAlignCenterCell)
                    ->addText(
                        $comment->getScaleIndex(),
                        $this->normalFont,
                        $this->centerParagraph
                    );

                $this->customizableCell['BgColor'] = $comment->getColour();

                $table->addCell(Converter::cmToTwip(8.00), $this->customizableCell)
                    ->addText(
                        _WT($translationComment !== null ? $translationComment->getValue() : ''),
                        $this->normalFont,
                        $this->leftParagraph
                    );
            }
        }
        return $table;
    }

    /**
     * Generates the Statement Of Applicability data
     * @return mixed|string The WordXml data generated
     */
    protected function generateTableStatementOfApplicability($referential, $translations)
    {
        /** @var SoaService $soaService */
        $soaService = $this->soaService;
        $filterMeasures['r.anr'] = $this->anr->getId();
        $filterMeasures['r.uuid'] = $referential;
        $measureService = $this->measureService;
        $measuresFiltered = $measureService->getList(1, 0, null, null, $filterMeasures);
        $measuresFilteredId = [];
        foreach ($measuresFiltered as $key) {
            array_push($measuresFilteredId, $key['uuid']);
        }
        $filterAnd['m.uuid'] = [
            'op' => 'IN',
            'value' => $measuresFilteredId,
        ];
        $filterAnd['m.anr'] = $this->anr->getId();
        $controlSoaList = $soaService->getList(1, 0, 'm.code', null, $filterAnd);

        //create section
        $tableWord = new PhpWord();
        $section = $tableWord->addSection();
        $table = $section->addTable($this->borderTable);

        //header if array is not empty
        if (count($controlSoaList)) {
            $table->addRow(400, $this->tblHeader);
            $table->addCell(Converter::cmToTwip(1.00), $this->grayCell)
                ->addText(
                    $this->anrTranslate('Code'),
                    $this->boldFont,
                    $this->centerParagraph
                );
            $table->addCell(Converter::cmToTwip(5.00), $this->grayCell)
                ->addText(
                    $this->anrTranslate('Control'),
                    $this->boldFont,
                    $this->centerParagraph
                );
            $table->addCell(Converter::cmToTwip(4.00), $this->grayCell)
                ->addText(
                    $this->anrTranslate('Inclusion/Exclusion'),
                    $this->boldFont,
                    $this->centerParagraph
                );
            $table->addCell(Converter::cmToTwip(5.00), $this->grayCell)
                ->addText(
                    $this->anrTranslate('Remarks/Justification'),
                    $this->boldFont,
                    $this->centerParagraph
                );
            $table->addCell(Converter::cmToTwip(5.00), $this->grayCell)
                ->addText(
                    $this->anrTranslate('Evidences'),
                    $this->boldFont,
                    $this->centerParagraph
                );
            $table->addCell(Converter::cmToTwip(5.00), $this->grayCell)
                ->addText(
                    $this->anrTranslate('Actions'),
                    $this->boldFont,
                    $this->centerParagraph
                );
            $table->addCell(Converter::cmToTwip(2.00), $this->grayCell)
                ->addText(
                    $this->anrTranslate('Level of compliance'),
                    $this->boldFont,
                    $this->centerParagraph
                );
        }

        $inclusions = [
            'EX' => $this->anrTranslate('Excluded'),
            'LR' => $this->anrTranslate('Legal requirements'),
            'CO' => $this->anrTranslate('Contractual obligations'),
            'BR' => $this->anrTranslate('Business requirements'),
            'BP' => $this->anrTranslate('Best practices'),
            'RRA' => $this->anrTranslate('Results of risk assessment')
        ];

        $previousCatId = null;

        foreach ($controlSoaList as $controlSoa) {
            $getInclusions = [];
            foreach ($inclusions as $incl => $value) {
                if ($controlSoa[$incl]) {
                    $getInclusions[] = $value;
                }
            }
            $inclusion = join("\n\n", $getInclusions);

            $complianceLevel = "";
            $bgcolor = 'FFFFFF';

            if (!is_null($controlSoa['soaScaleComment']) && !$controlSoa['soaScaleComment']->isHidden()) {
                $translationComment = $translations[
                    $controlSoa['soaScaleComment']->getCommentTranslationKey()
                    ] ?? null;
                $complianceLevel = $translationComment !== null ? $translationComment->getValue() : '';
                $bgcolor = $controlSoa['soaScaleComment']->getColour();
            }

            if ($controlSoa['EX']) {
                $complianceLevel = "";
                $bgcolor = 'E7E6E6';
            }

            $styleContentCellCompliance = ['valign' => 'center', 'bgcolor' => $bgcolor];

            if ($controlSoa['measure']->category->id != $previousCatId) {
                $table->addRow(400);
                $table->addCell(Converter::cmToTwip(10.00), $this->setColSpanCell(7, 'DBE5F1'))
                    ->addText(
                        _WT($controlSoa['measure']->category->get('label' . $this->currentLangAnrIndex)),
                        $this->boldFont,
                        $this->leftParagraph
                    );
            }
            $previousCatId = $controlSoa['measure']->category->id;

            $table->addRow(400);
            $table->addCell(Converter::cmToTwip(1.00), $this->vAlignCenterCell)
                ->addText(
                    _WT($controlSoa['measure']->code),
                    $this->normalFont,
                    $this->centerParagraph
                );
            $table->addCell(Converter::cmToTwip(5.00), $this->vAlignCenterCell)
                ->addText(
                    _WT($controlSoa['measure']->get('label' . $this->currentLangAnrIndex)),
                    $this->normalFont,
                    $this->leftParagraph
                );
            $table->addCell(Converter::cmToTwip(4.00), $this->vAlignCenterCell)
                ->addText(
                    _WT($inclusion),
                    $this->normalFont,
                    $this->leftParagraph
                );
            $table->addCell(Converter::cmToTwip(5.00), $this->vAlignCenterCell)
                ->addText(
                    _WT($controlSoa['remarks']),
                    $this->normalFont,
                    $this->leftParagraph
                );
            $table->addCell(Converter::cmToTwip(5.00), $this->vAlignCenterCell)
                ->addText(
                    _WT($controlSoa['evidences']),
                    $this->normalFont,
                    $this->leftParagraph
                );
            $table->addCell(Converter::cmToTwip(5.00), $this->vAlignCenterCell)
                ->addText(
                    _WT($controlSoa['actions']),
                    $this->normalFont,
                    $this->leftParagraph
                );
            $table->addCell(Converter::cmToTwip(2.00), $styleContentCellCompliance)
                ->addText(
                    _WT($complianceLevel),
                    $this->normalFont,
                    $this->centerParagraph
                );
        }

        return $table;
    }

    /**
     * Generates the table risks by control in SOA
     * @return mixed|string The WordXml data generated
     */
    protected function generateTableRisksByControl($referential)
    {
        /** @var SoaService $soaService */
        $soaService = $this->soaService;
        $filterMeasures['r.anr'] = $this->anr->getId();
        $filterMeasures['r.uuid'] = $referential;
        $measureService = $this->measureService;
        $measuresFiltered = $measureService->getList(1, 0, null, null, $filterMeasures);
        $measuresFilteredId = [];
        foreach ($measuresFiltered as $key) {
            array_push($measuresFilteredId, $key['uuid']);
        }
        $filterAnd['m.uuid'] = [
            'op' => 'IN',
            'value' => $measuresFilteredId,
        ];
        $filterAnd['m.anr'] = $this->anr->getId();
        $controlSoaList = $soaService->getList(1, 0, 'm.code', null, $filterAnd);
        $opRisksAllScales = $this->operationalRiskScaleService->getOperationalRiskScales($this->anr->getId());
        $opRisksImpactsScaleType = array_values(array_filter($opRisksAllScales, function ($scale) {
            return $scale['type'] == 1;
        }));
        $opRisksImpactsScales = array_filter($opRisksImpactsScaleType[0]['scaleTypes'], function ($scale) {
            return $scale['isHidden'] == false;
        });
        $sizeCellImpact = count($opRisksImpactsScales) * 0.70;

        //create section
        $tableWord = new PhpWord();
        $section = $tableWord->addSection();

        $previousControlId = null;

        foreach ($controlSoaList as $controlSoa) {
            $amvs = [];
            $rolfRisks = [];
            foreach ($controlSoa['measure']->amvs as $amv) {
                $amvs[] = $amv->getUuid();
            }
            foreach ($controlSoa['measure']->rolfRisks as $rolfRisk) {
                $rolfRisks[] = $rolfRisk->getId();
            }
            $controlSoa['measure']->rolfRisks = [];
            if (!empty($rolfRisks)) {
                $controlSoa['measure']->rolfRisks = $this->get('anrInstanceRiskOpService')->getOperationalRisks(
                    $this->anr,
                    null,
                    ['rolfRisks' => $rolfRisks, 'limit' => -1, 'order' => 'cacheNetRisk', 'order_direction' => 'desc']
                );
            }
            if (!empty($amvs)) {
                $controlSoa['measure']->amvs = $this->get('anrInstanceRiskService')->getInstanceRisks(
                    $this->anr->getId(),
                    null,
                    ['amvs' => $amvs, 'limit' => -1, 'order' => 'maxRisk', 'order_direction' => 'desc']
                );
            }

            if (count($controlSoa['measure']->amvs) || count($controlSoa['measure']->rolfRisks)) {
                if ($controlSoa['measure']->getUuid() != $previousControlId) {
                    $section->addText(
                        _WT(
                            $controlSoa['measure']->code
                        ) .
                            ' - ' .
                            _WT(
                                $controlSoa['measure']->get('label' .
                                $this->currentLangAnrIndex)
                            ),
                        array_merge($this->boldFont, ['size' => 11])
                    );

                    if (count($controlSoa['measure']->amvs)) {
                        $section->addText(
                            $this->anrTranslate('Information risks'),
                            $this->boldFont
                        );
                        $tableRiskInfo = $section->addTable($this->borderTable);

                        $tableRiskInfo->addRow(400);
                        $tableRiskInfo->addCell(Converter::cmToTwip(3.00), $this->restartAndGrayCell)
                            ->addText(
                                $this->anrTranslate('Asset'),
                                $this->boldFont,
                                $this->centerParagraph
                            );
                        $tableRiskInfo->addCell(Converter::cmToTwip(2.10), $this->setColSpanCell(3, 'DFDFDF'))
                            ->addText(
                                $this->anrTranslate('Impact'),
                                $this->boldFont,
                                $this->centerParagraph
                            );
                        $tableRiskInfo->addCell(Converter::cmToTwip(4.50), $this->setColSpanCell(2, 'DFDFDF'))
                            ->addText(
                                $this->anrTranslate('Threat'),
                                $this->boldFont,
                                $this->centerParagraph
                            );
                        $tableRiskInfo->addCell(Converter::cmToTwip(10.00), $this->setColSpanCell(3, 'DFDFDF'))
                            ->addText(
                                $this->anrTranslate('Vulnerability'),
                                $this->boldFont,
                                $this->centerParagraph
                            );
                        $tableRiskInfo->addCell(Converter::cmToTwip(3.00), $this->setColSpanCell(3, 'DFDFDF'))
                            ->addText(
                                $this->anrTranslate('Current risk'),
                                $this->boldFont,
                                $this->centerParagraph
                            );
                        $tableRiskInfo->addCell(Converter::cmToTwip(3.00), $this->restartAndGrayCell)
                            ->addText(
                                $this->anrTranslate('Treatment'),
                                $this->boldFont,
                                $this->centerParagraph
                            );
                        $tableRiskInfo->addCell(Converter::cmToTwip(1.50), $this->restartAndGrayCell)
                            ->addText(
                                $this->anrTranslate('Residual risk'),
                                $this->boldFont,
                                $this->centerParagraph
                            );
                        $tableRiskInfo->addRow(400);
                        $tableRiskInfo->addCell(Converter::cmToTwip(3.00), $this->continueAndGrayCell);
                        $tableRiskInfo->addCell(Converter::cmToTwip(0.70), $this->grayCell)
                            ->addText(
                                'C',
                                $this->boldFont,
                                $this->centerParagraph
                            );
                        $tableRiskInfo->addCell(Converter::cmToTwip(0.70), $this->grayCell)
                            ->addText(
                                'I',
                                $this->boldFont,
                                $this->centerParagraph
                            );
                        $tableRiskInfo->addCell(Converter::cmToTwip(0.70), $this->grayCell)
                            ->addText(
                                $this->anrTranslate('A'),
                                $this->boldFont,
                                $this->centerParagraph
                            );
                        $tableRiskInfo->addCell(Converter::cmToTwip(2.50), $this->grayCell)
                            ->addText(
                                $this->anrTranslate('Label'),
                                $this->boldFont,
                                $this->centerParagraph
                            );
                        $tableRiskInfo->addCell(Converter::cmToTwip(2.00), $this->grayCell)
                            ->addText(
                                $this->anrTranslate('Prob.'),
                                $this->boldFont,
                                $this->centerParagraph
                            );
                        $tableRiskInfo->addCell(Converter::cmToTwip(3.00), $this->grayCell)
                            ->addText(
                                $this->anrTranslate('Label'),
                                $this->boldFont,
                                $this->centerParagraph
                            );
                        $tableRiskInfo->addCell(Converter::cmToTwip(4.00), $this->grayCell)
                            ->addText(
                                $this->anrTranslate('Existing controls'),
                                $this->boldFont,
                                $this->centerParagraph
                            );
                        $tableRiskInfo->addCell(Converter::cmToTwip(3.00), $this->grayCell)
                            ->addText(
                                $this->anrTranslate('Qualif.'),
                                $this->boldFont,
                                $this->centerParagraph
                            );
                        $tableRiskInfo->addCell(Converter::cmToTwip(1.00), $this->grayCell)
                            ->addText(
                                'C',
                                $this->boldFont,
                                $this->centerParagraph
                            );
                        $tableRiskInfo->addCell(Converter::cmToTwip(1.00), $this->grayCell)
                            ->addText(
                                'I',
                                $this->boldFont,
                                $this->centerParagraph
                            );
                        $tableRiskInfo->addCell(Converter::cmToTwip(1.00), $this->grayCell)
                            ->addText(
                                $this->anrTranslate('A'),
                                $this->boldFont,
                                $this->centerParagraph
                            );
                        $tableRiskInfo->addCell(Converter::cmToTwip(3.00), $this->continueAndGrayCell);
                        $tableRiskInfo->addCell(Converter::cmToTwip(1.50), $this->continueAndGrayCell);
                    }
                    if (count($controlSoa['measure']->rolfRisks)) {
                        $section->addText(
                            $this->anrTranslate('Operational risks'),
                            $this->boldFont
                        );
                        $tableRiskOp = $section->addTable($this->borderTable);

                        $tableRiskOp->addRow(400);
                        $tableRiskOp->addCell(Converter::cmToTwip(3.00), $this->restartAndGrayCell)
                            ->addText(
                                $this->anrTranslate('Asset'),
                                $this->boldFont,
                                $this->centerParagraph
                            );
                        $tableRiskOp->addCell(Converter::cmToTwip(10.00), $this->restartAndGrayCell)
                            ->addText(
                                $this->anrTranslate('Risk description'),
                                $this->boldFont,
                                $this->centerParagraph
                            );
                        if ($this->anr->showRolfBrut == 1) {
                            $tableRiskOp
                                ->addCell(
                                    Converter::cmToTwip(5.50),
                                    $this->setColSpanCell(2 + count($opRisksImpactsScales), 'DFDFDF')
                                )
                                ->addText(
                                    $this->anrTranslate('Inherent risk'),
                                    $this->boldFont,
                                    $this->centerParagraph
                                );
                        }
                        $tableRiskOp
                            ->addCell(
                                Converter::cmToTwip(15.00),
                                $this->setColSpanCell(3 + count($opRisksImpactsScales), 'DFDFDF')
                            )
                            ->addText(
                                $this->anrTranslate('Net risk'),
                                $this->boldFont,
                                $this->centerParagraph
                            );
                        $tableRiskOp->addCell(Converter::cmToTwip(2.00), $this->restartAndGrayCell)
                            ->addText(
                                $this->anrTranslate('Treatment'),
                                $this->boldFont,
                                $this->centerParagraph
                            );
                        $tableRiskOp->addCell(Converter::cmToTwip(2.00), $this->restartAndGrayCell)
                            ->addText(
                                $this->anrTranslate('Residual risk'),
                                $this->boldFont,
                                $this->centerParagraph
                            );

                        $tableRiskOp->addRow(400, $this->tblHeader);
                        $tableRiskOp->addCell(Converter::cmToTwip(3.00), $this->continueAndGrayCell);
                        $tableRiskOp->addCell(Converter::cmToTwip(10.00), $this->continueAndGrayCell);
                        if ($this->anr->showRolfBrut == 1) {
                            $tableRiskOp->addCell(Converter::cmToTwip(1.00), $this->restartAndGrayCell)
                                ->addText(
                                    $this->anrTranslate('Prob.'),
                                    $this->boldFont,
                                    $this->centerParagraph
                                );
                            $tableRiskOp
                                ->addCell(
                                    Converter::cmToTwip($sizeCellImpact),
                                    $this->setColSpanCell(count($opRisksImpactsScales), 'DFDFDF')
                                )
                                ->addText(
                                    $this->anrTranslate('Impact'),
                                    $this->boldFont,
                                    $this->centerParagraph
                                );
                            $tableRiskOp->addCell(Converter::cmToTwip(1.00), $this->restartAndGrayCell)
                                ->addText(
                                    $this->anrTranslate('Current risk'),
                                    $this->boldFont,
                                    $this->centerParagraph
                                );
                        }
                        $tableRiskOp->addCell(Converter::cmToTwip(1.00), $this->restartAndGrayCell)
                            ->addText(
                                $this->anrTranslate('Prob.'),
                                $this->boldFont,
                                $this->centerParagraph
                            );
                        $tableRiskOp
                            ->addCell(
                                Converter::cmToTwip($sizeCellImpact),
                                $this->setColSpanCell(count($opRisksImpactsScales), 'DFDFDF')
                            )
                            ->addText(
                                $this->anrTranslate('Impact'),
                                $this->boldFont,
                                $this->centerParagraph
                            );
                        $tableRiskOp->addCell(Converter::cmToTwip(1.00), $this->restartAndGrayCell)
                            ->addText(
                                $this->anrTranslate('Current risk'),
                                $this->boldFont,
                                $this->centerParagraph
                            );
                        $tableRiskOp->addCell(Converter::cmToTwip(8.00), $this->restartAndGrayCell)
                            ->addText(
                                $this->anrTranslate('Existing controls'),
                                $this->boldFont,
                                $this->centerParagraph
                            );
                        $tableRiskOp->addCell(Converter::cmToTwip(2.00), $this->continueAndGrayCell);
                        $tableRiskOp->addCell(Converter::cmToTwip(2.00), $this->continueAndGrayCell);

                        $tableRiskOp->addRow(Converter::cmToTwip(1.00), ['tblHeader' => true]);
                        $tableRiskOp->addCell(Converter::cmToTwip(3.00), $this->continueAndGrayCell);
                        $tableRiskOp->addCell(Converter::cmToTwip(10.00), $this->continueAndGrayCell);
                        if ($this->anr->showRolfBrut == 1) {
                            $tableRiskOp->addCell(Converter::cmToTwip(1.00), $this->continueAndGrayCell);
                            foreach ($opRisksImpactsScales as $opRiskImpactScale) {
                                $label = mb_substr(_WT($opRiskImpactScale['label']), 0, 3) . '.';
                                $tableRiskOp
                                    ->addCell(
                                        Converter::cmToTwip(0.70),
                                        array_merge($this->rotate90TextCell, ['bgcolor' => 'DFDFDF'])
                                    )
                                    ->addText(
                                        $label,
                                        $this->boldFont,
                                        $this->verticalCenterParagraph
                                    );
                            }
                            $tableRiskOp->addCell(Converter::cmToTwip(1.00), $this->continueAndGrayCell);
                        }
                        $tableRiskOp->addCell(Converter::cmToTwip(1.00), $this->continueAndGrayCell);
                        foreach ($opRisksImpactsScales as $opRiskImpactScale) {
                            $label = mb_substr(_WT($opRiskImpactScale['label']), 0, 3) . '.';
                            $tableRiskOp
                                ->addCell(
                                    Converter::cmToTwip(0.70),
                                    array_merge($this->rotate90TextCell, ['bgcolor' => 'DFDFDF'])
                                )
                                ->addText(
                                    $label,
                                    $this->boldFont,
                                    $this->verticalCenterParagraph
                                );
                        }
                        $tableRiskOp->addCell(Converter::cmToTwip(1.00), $this->continueAndGrayCell);
                        $tableRiskOp->addCell(Converter::cmToTwip(8.00), $this->continueAndGrayCell);
                        $tableRiskOp->addCell(Converter::cmToTwip(2.00), $this->continueAndGrayCell);
                        $tableRiskOp->addCell(Converter::cmToTwip(2.00), $this->continueAndGrayCell);
                    }
                }
                $previousControlId = $controlSoa['measure']->getUuid();
                if (!empty($controlSoa['measure']->amvs)) {
                    $impacts = ['c', 'i', 'd'];

                    foreach ($controlSoa['measure']->amvs as $r) {
                        foreach ($impacts as $impact) {
                            if ($r[$impact . '_risk_enabled'] == 0) {
                                $r[$impact . '_risk'] = null;
                            }
                        }

                        foreach ($r as $key => $value) {
                            if ($value == -1) {
                                $r[$key] = '-';
                            }
                        }

                        $instance = $this->instanceTable->findById($r['instance']);
                        if (!$instance->getObject()->isScopeGlobal()) {
                            $path = $instance->getHierarchyString();
                        } else {
                            $path = $instance->{
                                'name' .
                                $this->currentLangAnrIndex} .
                                ' (' .
                                $this->anrTranslate('Global') .
                                ')';
                        }

                        $tableRiskInfo->addRow(400);
                        $tableRiskInfo->addCell(Converter::cmToTwip(3.00), $this->vAlignCenterCell)
                            ->addText(
                                _WT($path),
                                $this->normalFont,
                                $this->leftParagraph
                            );
                        $tableRiskInfo->addCell(Converter::cmToTwip(0.70), $this->vAlignCenterCell)
                            ->addText(
                                $r['c_impact'],
                                $this->normalFont,
                                $this->centerParagraph
                            );
                        $tableRiskInfo->addCell(Converter::cmToTwip(0.70), $this->vAlignCenterCell)
                            ->addText(
                                $r['i_impact'],
                                $this->normalFont,
                                $this->centerParagraph
                            );
                        $tableRiskInfo->addCell(Converter::cmToTwip(0.70), $this->vAlignCenterCell)
                            ->addText(
                                $r['d_impact'],
                                $this->normalFont,
                                $this->centerParagraph
                            );
                        $tableRiskInfo->addCell(Converter::cmToTwip(2.50), $this->vAlignCenterCell)
                            ->addText(
                                _WT($r['threatLabel' . $this->currentLangAnrIndex]),
                                $this->normalFont,
                                $this->leftParagraph
                            );
                        $tableRiskInfo->addCell(Converter::cmToTwip(2.00), $this->vAlignCenterCell)
                            ->addText(
                                $r['threatRate'],
                                $this->normalFont,
                                $this->centerParagraph
                            );
                        $tableRiskInfo->addCell(Converter::cmToTwip(3.00), $this->vAlignCenterCell)
                            ->addText(
                                _WT($r['vulnLabel' . $this->currentLangAnrIndex]),
                                $this->normalFont,
                                $this->leftParagraph
                            );
                        $tableRiskInfo->addCell(Converter::cmToTwip(4.00), $this->vAlignCenterCell)
                            ->addText(
                                _WT($r['comment']),
                                $this->normalFont,
                                $this->leftParagraph
                            );
                        $tableRiskInfo->addCell(Converter::cmToTwip(3.00), $this->vAlignCenterCell)
                            ->addText(
                                $r['vulnerabilityRate'],
                                $this->normalFont,
                                $this->centerParagraph
                            );
                        $tableRiskInfo->addCell(Converter::cmToTwip(1.00), $this->setBgColorCell($r['c_risk']))
                            ->addText(
                                $r['c_risk'],
                                $this->boldFont,
                                $this->centerParagraph
                            );
                        $tableRiskInfo->addCell(Converter::cmToTwip(1.00), $this->setBgColorCell($r['i_risk']))
                            ->addText(
                                $r['i_risk'],
                                $this->boldFont,
                                $this->centerParagraph
                            );
                        $tableRiskInfo->addCell(Converter::cmToTwip(1.00), $this->setBgColorCell($r['d_risk']))
                            ->addText(
                                $r['d_risk'],
                                $this->boldFont,
                                $this->centerParagraph
                            );
                        $tableRiskInfo->addCell(Converter::cmToTwip(3.00), $this->vAlignCenterCell)
                            ->addText(
                                $this->getKindfofMeasureLabel($r['kindOfMeasure']),
                                $this->normalFont,
                                $this->leftParagraph
                            );
                        $tableRiskInfo->addCell(Converter::cmToTwip(1.50), $this->setBgColorCell($r['target_risk']))
                            ->addText(
                                $r['target_risk'],
                                $this->boldFont,
                                $this->centerParagraph
                            );
                    }
                }

                if (count($controlSoa['measure']->rolfRisks)) {
                    $kindOfRisks = ['cacheBrutRisk', 'cacheNetRisk', 'cacheTargetedRisk'];

                    foreach ($controlSoa['measure']->rolfRisks as $r) {
                        foreach ($r as $key => $value) {
                            if ($value == -1) {
                                $r[$key] = '-';
                            }
                        }

                        $instance = $this->instanceTable->findById($r['instanceInfos']['id']);
                        $path = $instance->getHierarchyString();

                        $tableRiskOp->addRow(400);
                        $tableRiskOp->addCell(Converter::cmToTwip(3.00), $this->vAlignCenterCell)
                            ->addText(
                                _WT($path),
                                $this->normalFont,
                                $this->leftParagraph
                            );
                        $tableRiskOp->addCell(Converter::cmToTwip(10.00), $this->vAlignCenterCell)
                            ->addText(
                                _WT($r['label' . $this->currentLangAnrIndex]),
                                $this->normalFont,
                                $this->leftParagraph
                            );
                        if ($this->anr->showRolfBrut == 1) {
                            $tableRiskOp->addCell(Converter::cmToTwip(1.00), $this->vAlignCenterCell)
                                ->addText(
                                    $r['brutProb'],
                                    $this->normalFont,
                                    $this->centerParagraph
                                );
                            foreach ($opRisksImpactsScales as $opRiskImpactScale) {
                                $tableRiskOp->addCell(Converter::cmToTwip(0.70), $this->vAlignCenterCell)
                                    ->addText(
                                        $r['scales'][$opRiskImpactScale['id']]['brutValue'] !== -1 ?
                                            $r['scales'][$opRiskImpactScale['id']]['brutValue'] :
                                            '-',
                                        $this->normalFont,
                                        $this->centerParagraph
                                    );
                            }
                            $tableRiskOp
                                ->addCell(
                                    Converter::cmToTwip(1.00),
                                    $this->setBgColorCell($r['cacheBrutRisk'], false)
                                )
                                ->addText(
                                    $r['cacheBrutRisk'],
                                    $this->boldFont,
                                    $this->centerParagraph
                                );
                        }
                        $tableRiskOp->addCell(Converter::cmToTwip(1.00), $this->vAlignCenterCell)
                            ->addText(
                                $r['netProb'],
                                $this->normalFont,
                                $this->centerParagraph
                            );
                        foreach ($opRisksImpactsScales as $opRiskImpactScale) {
                            $tableRiskOp->addCell(Converter::cmToTwip(0.70), $this->vAlignCenterCell)
                                ->addText(
                                    $r['scales'][$opRiskImpactScale['id']]['netValue'] !== -1 ?
                                        $r['scales'][$opRiskImpactScale['id']]['netValue'] :
                                        '-',
                                    $this->normalFont,
                                    $this->centerParagraph
                                );
                        }
                        $tableRiskOp
                            ->addCell(
                                Converter::cmToTwip(1.00),
                                $this->setBgColorCell($r['cacheNetRisk'], false)
                            )
                            ->addText(
                                $r['cacheNetRisk'],
                                $this->boldFont,
                                $this->centerParagraph
                            );
                        $tableRiskOp->addCell(Converter::cmToTwip(8.00), $this->vAlignCenterCell)
                            ->addText(
                                _WT($r['comment']),
                                $this->normalFont,
                                $this->leftParagraph
                            );
                        $tableRiskOp->addCell(Converter::cmToTwip(2.00), $this->vAlignCenterCell)
                            ->addText(
                                $this->getKindfofMeasureLabel($r['kindOfMeasure']),
                                $this->normalFont,
                                $this->leftParagraph
                            );
                        $cacheTargetedRisk = $r['cacheTargetedRisk'] == '-' ?
                            $r['cacheNetRisk'] :
                            $r['cacheTargetedRisk'];
                        $tableRiskOp
                            ->addCell(
                                Converter::cmToTwip(2.00),
                                $this->setBgColorCell($cacheTargetedRisk, false)
                            )
                            ->addText(
                                $cacheTargetedRisk,
                                $this->boldFont,
                                $this->centerParagraph
                            );
                    }
                }
                $section->addTextBreak();
            }
        }

        return $this->getWordXmlFromWordObject($tableWord);
    }

    /**
     * Generates the Processing Activities Record's General Informations data
     * @return mixed|string The WordXml data generated
     */
    protected function generateTableRecordGDPR($recordId)
    {
        $recordTable = $this->get('recordService')->get('table');
        $recordEntity = $recordTable->getEntity($recordId);

        $tableWord = new PhpWord();
        $tableWord->getSettings()->setUpdateFields(true);
        $section = $tableWord->addSection();
        $table = $section->addTable($this->borderTable);
        $table->addRow(400);
        $table->addCell(Converter::cmToTwip(4.00), $this->grayCell)
            ->addText(
                $this->anrTranslate('Name'),
                $this->boldFont,
                $this->leftParagraph
            );
        $table->addCell(Converter::cmToTwip(14.00), $this->vAlignCenterCell)
            ->addText(
                _WT($recordEntity->get('label')),
                $this->normalFont,
                $this->leftParagraph
            );
        $table->addRow(400);
        $table->addCell(Converter::cmToTwip(4.00), $this->grayCell)
            ->addText(
                $this->anrTranslate('Creation date'),
                $this->boldFont,
                $this->leftParagraph
            );
        $table->addCell(Converter::cmToTwip(14.00), $this->vAlignCenterCell)
            ->addText(
                ($recordEntity->get('createdAt') ?
                    strftime("%d-%m-%Y", $recordEntity->get('createdAt')->getTimeStamp()) :
                    ""
                ),
                $this->normalFont,
                $this->leftParagraph
            );
        $table->addRow(400);
        $table->addCell(Converter::cmToTwip(4.00), $this->grayCell)
            ->addText(
                $this->anrTranslate('Update date'),
                $this->boldFont,
                $this->leftParagraph
            );
        $table->addCell(Converter::cmToTwip(14.00), $this->vAlignCenterCell)
            ->addText(
                ($recordEntity->get('updatedAt') ?
                    strftime("%d-%m-%Y", $recordEntity->get('updatedAt')->getTimeStamp()) :
                    ""
                ),
                $this->normalFont,
                $this->leftParagraph
            );
        $table->addRow(400);
        $table->addCell(Converter::cmToTwip(4.00), $this->grayCell)
            ->addText(
                $this->anrTranslate('Purpose(s)'),
                $this->boldFont,
                $this->leftParagraph
            );
        $table->addCell(Converter::cmToTwip(14.00), $this->vAlignCenterCell)
            ->addText(
                _WT($recordEntity->get('purposes')),
                $this->normalFont,
                $this->leftParagraph
            );
        $table->addRow(400);
        $table->addCell(Converter::cmToTwip(4.00), $this->grayCell)
            ->addText(
                $this->anrTranslate('Security measures'),
                $this->boldFont,
                $this->leftParagraph
            );
        $table->addCell(Converter::cmToTwip(14.00), $this->vAlignCenterCell)
            ->addText(
                _WT($recordEntity->get('secMeasures')),
                $this->normalFont,
                $this->leftParagraph
            );

        return $this->getWordXmlFromWordObject($tableWord);
    }

    /**
     * Generates the Processing Activities Record's Joint Controllers data
     * @return mixed|string The WordXml data generated
     */
    protected function generateTableRecordActors($recordId)
    {
        $recordTable = $this->get('recordService')->get('table');
        $recordEntity = $recordTable->getEntity($recordId);
        $jointControllers = $recordEntity->get('jointControllers');

        //create section
        $tableWord = new PhpWord();
        $section = $tableWord->addSection();
        $table = $section->addTable($this->borderTable);

        //header if array is not empty
        $table->addRow(400);
        $table->addCell(Converter::cmToTwip(6.00), $this->grayCell)
            ->addText(
                $this->anrTranslate('Actor'),
                $this->boldFont,
                $this->centerParagraph
            );
        $table->addCell(Converter::cmToTwip(6.00), $this->grayCell)
            ->addText(
                $this->anrTranslate('Name'),
                $this->boldFont,
                $this->centerParagraph
            );
        $table->addCell(Converter::cmToTwip(6.00), $this->grayCell)
            ->addText(
                $this->anrTranslate('Contact'),
                $this->boldFont,
                $this->centerParagraph
            );

        $table->addRow(400);
        $table->addCell(Converter::cmToTwip(6.00), $this->grayCell)
            ->addText(
                $this->anrTranslate('Controller'),
                $this->boldFont,
                $this->leftParagraph
            );
        $table->addCell(Converter::cmToTwip(6.00), $this->vAlignCenterCell)
            ->addText(
                _WT($recordEntity->get('controller') ?
                    $recordEntity->get('controller')->get('label') :
                    ""),
                $this->normalFont,
                $this->leftParagraph
            );
        $table->addCell(Converter::cmToTwip(6.00), $this->vAlignCenterCell)
            ->addText(
                _WT($recordEntity->get('controller') ?
                    $recordEntity->get('controller')->get('contact') :
                    ""),
                $this->normalFont,
                $this->leftParagraph
            );

        $table->addRow(400);
        $table->addCell(Converter::cmToTwip(6.00), $this->grayCell)
            ->addText(
                $this->anrTranslate('Representative'),
                $this->boldFont,
                $this->leftParagraph
            );
        $table->addCell(Converter::cmToTwip(6.00), $this->vAlignCenterCell)
            ->addText(
                _WT($recordEntity->get('representative') ?
                    $recordEntity->get('representative')->get('label') :
                    ""),
                $this->normalFont,
                $this->leftParagraph
            );
        $table->addCell(Converter::cmToTwip(6.00), $this->vAlignCenterCell)
            ->addText(
                _WT($recordEntity->get('representative') ?
                    $recordEntity->get('representative')->get('contact') :
                    ""),
                $this->normalFont,
                $this->leftParagraph
            );

        $table->addRow(400);
        $table->addCell(Converter::cmToTwip(6.00), $this->grayCell)
            ->addText(
                $this->anrTranslate('Data protection officer'),
                $this->boldFont,
                $this->leftParagraph
            );
        $table->addCell(Converter::cmToTwip(6.00), $this->vAlignCenterCell)
            ->addText(
                _WT($recordEntity->get('dpo') ?
                    $recordEntity->get('dpo')->get('label') :
                    ""),
                $this->normalFont,
                $this->leftParagraph
            );
        $table->addCell(Converter::cmToTwip(6.00), $this->vAlignCenterCell)
            ->addText(
                _WT($recordEntity->get('dpo') ?
                    $recordEntity->get('dpo')->get('contact') :
                    ""),
                $this->normalFont,
                $this->leftParagraph
            );

        $table->addRow(400);
        $table->addCell(Converter::cmToTwip(6.00), $this->grayCell)
            ->addText(
                $this->anrTranslate('Joint controllers'),
                $this->boldFont,
                $this->leftParagraph
            );

        if (count($jointControllers)) {
            $i = 0;
            foreach ($jointControllers as $jc) {
                $table->addCell(Converter::cmToTwip(6.00), $this->vAlignCenterCell)
                    ->addText(
                        _WT($jc->get('label')),
                        $this->normalFont,
                        $this->leftParagraph
                    );
                $table->addCell(Converter::cmToTwip(6.00), $this->vAlignCenterCell)
                    ->addText(
                        _WT($jc->get('contact')),
                        $this->normalFont,
                        $this->leftParagraph
                    );
                if ($i != count($jointControllers) - 1) {
                    $table->addRow(400);
                    $table->addCell(Converter::cmToTwip(6.00), $this->grayCell);
                }
                ++$i;
            }
        } else {
            $table->addCell(Converter::cmToTwip(6.00), $this->vAlignCenterCell);
            $table->addCell(Converter::cmToTwip(6.00), $this->vAlignCenterCell);
        }

        return $this->getWordXmlFromWordObject($tableWord);
    }

    /**
     * Generates the Processing Activities Record's Personal data data
     * @return mixed|string The WordXml data generated
     */
    protected function generateTableRecordPersonalData($recordId)
    {
        $recordTable = $this->get('recordService')->get('table');
        $recordEntity = $recordTable->getEntity($recordId);
        $personalData = $recordEntity->get('personalData');

        //create section
        $tableWord = new PhpWord();
        $section = $tableWord->addSection();

        if (count($personalData)) {
            $table = $section->addTable($this->borderTable);

            //header if array is not empty
            $table->addRow(400);
            $table->addCell(Converter::cmToTwip(3.60), $this->grayCell)
                ->addText(
                    $this->anrTranslate('Data subject'),
                    $this->boldFont,
                    $this->centerParagraph
                );
            $table->addCell(Converter::cmToTwip(3.60), $this->grayCell)
                ->addText(
                    $this->anrTranslate('Personal data categories'),
                    $this->boldFont,
                    $this->centerParagraph
                );
            $table->addCell(Converter::cmToTwip(3.60), $this->grayCell)
                ->addText(
                    $this->anrTranslate('Description'),
                    $this->boldFont,
                    $this->centerParagraph
                );
            $table->addCell(Converter::cmToTwip(3.60), $this->grayCell)
                ->addText(
                    $this->anrTranslate('Retention period'),
                    $this->boldFont,
                    $this->centerParagraph
                );
            $table->addCell(Converter::cmToTwip(3.60), $this->grayCell)
                ->addText(
                    $this->anrTranslate('Retention period description'),
                    $this->boldFont,
                    $this->centerParagraph
                );

            foreach ($personalData as $pd) {
                $table->addRow(400);
                $dataCategories = '';
                foreach ($pd->get('dataCategories') as $dc) {
                    $dataCategories .= $dc->get('label') . "\n";
                }
                $retentionPeriod = $pd->get('retentionPeriod') . ' ';
                if ($pd->get('retentionPeriodMode') == 0) {
                    $retentionPeriod .= $this->anrTranslate('day(s)');
                } else {
                    if ($pd->get('retentionPeriodMode') == 1) {
                        $retentionPeriod .= $this->anrTranslate('month(s)');
                    } else {
                        $retentionPeriod .= $this->anrTranslate('year(s)');
                    }
                }
                $table->addCell(Converter::cmToTwip(3.60), $this->vAlignCenterCell)
                    ->addText(
                        _WT($pd->get('dataSubject')),
                        $this->normalFont,
                        $this->leftParagraph
                    );
                $table->addCell(Converter::cmToTwip(3.60), $this->vAlignCenterCell)
                    ->addText(
                        _WT($dataCategories),
                        $this->normalFont,
                        $this->leftParagraph
                    );
                $table->addCell(Converter::cmToTwip(3.60), $this->vAlignCenterCell)
                    ->addText(
                        _WT($pd->get('description')),
                        $this->normalFont,
                        $this->leftParagraph
                    );
                $table->addCell(Converter::cmToTwip(3.60), $this->vAlignCenterCell)
                    ->addText(
                        _WT($retentionPeriod),
                        $this->normalFont,
                        $this->leftParagraph
                    );
                $table->addCell(Converter::cmToTwip(3.60), $this->vAlignCenterCell)
                    ->addText(
                        _WT($pd->get('retentionPeriodDescription')),
                        $this->normalFont,
                        $this->leftParagraph
                    );
            }
        } else {
            $table = $section->addText(
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
    protected function generateTableRecordRecipients($recordId)
    {
        $recordTable = $this->get('recordService')->get('table');
        $recordEntity = $recordTable->getEntity($recordId);
        $recipients = $recordEntity->get('recipients');

        //create section
        $tableWord = new PhpWord();
        $section = $tableWord->addSection();

        if (count($recipients)) {
            $table = $section->addTable($this->borderTable);

            //header if array is not empty
            $table->addRow(400);
            $table->addCell(Converter::cmToTwip(6.00), $this->grayCell)
                ->addText(
                    $this->anrTranslate('Recipient'),
                    $this->boldFont,
                    $this->centerParagraph
                );
            $table->addCell(Converter::cmToTwip(4.00), $this->grayCell)
                ->addText(
                    $this->anrTranslate('Type'),
                    $this->boldFont,
                    $this->centerParagraph
                );
            $table->addCell(Converter::cmToTwip(8.00), $this->grayCell)
                ->addText(
                    $this->anrTranslate('Description'),
                    $this->boldFont,
                    $this->centerParagraph
                );

            foreach ($recipients as $r) {
                $table->addRow(400);
                $table->addCell(Converter::cmToTwip(6.00), $this->vAlignCenterCell)
                    ->addText(
                        _WT($r->get('label')),
                        $this->normalFont,
                        $this->leftParagraph
                    );
                $table->addCell(Converter::cmToTwip(4.00), $this->vAlignCenterCell)
                    ->addText(
                        $r->get('type') == 0 ?
                            $this->anrTranslate('internal') :
                            $this->anrTranslate('external'),
                        $this->normalFont,
                        $this->leftParagraph
                    );
                $table->addCell(Converter::cmToTwip(8.00), $this->vAlignCenterCell)
                    ->addText(
                        _WT($r->get('description')),
                        $this->normalFont,
                        $this->leftParagraph
                    );
            }
        } else {
            $table = $section->addText(
                $this->anrTranslate('No recipient'),
                $this->normalFont,
                $this->leftParagraph
            );
        }

        return $this->getWordXmlFromWordObject($tableWord);
    }

    /**
     * Generates the Processing Activities Record's International Transfers data
     * @return mixed|string The WordXml data generated
     */
    protected function generateTableRecordInternationalTransfers($recordId)
    {
        $recordTable = $this->get('recordService')->get('table');
        $recordEntity = $recordTable->getEntity($recordId);
        $internationalTransfers = $recordEntity->get('internationalTransfers');

        //create section
        $tableWord = new PhpWord();
        $section = $tableWord->addSection();

        if (count($internationalTransfers)) {
            $table = $section->addTable($this->borderTable);

            //header if array is not empty
            $table->addRow(400);
            $table->addCell(Converter::cmToTwip(4.50), $this->grayCell)
                ->addText(
                    $this->anrTranslate('Organisation'),
                    $this->boldFont,
                    $this->centerParagraph
                );
            $table->addCell(Converter::cmToTwip(4.50), $this->grayCell)
                ->addText(
                    $this->anrTranslate('Description'),
                    $this->boldFont,
                    $this->centerParagraph
                );
            $table->addCell(Converter::cmToTwip(4.50), $this->grayCell)
                ->addText(
                    $this->anrTranslate('Country'),
                    $this->boldFont,
                    $this->centerParagraph
                );
            $table->addCell(Converter::cmToTwip(4.50), $this->grayCell)
                ->addText(
                    $this->anrTranslate('Documents'),
                    $this->boldFont,
                    $this->centerParagraph
                );

            foreach ($internationalTransfers as $it) {
                $table->addRow(400);
                $table->addCell(Converter::cmToTwip(4.50), $this->vAlignCenterCell)
                    ->addText(
                        _WT($it->get('organisation')),
                        $this->normalFont,
                        $this->leftParagraph
                    );
                $table->addCell(Converter::cmToTwip(4.50), $this->vAlignCenterCell)
                    ->addText(
                        _WT($it->get('description')),
                        $this->normalFont,
                        $this->leftParagraph
                    );
                $table->addCell(Converter::cmToTwip(4.50), $this->vAlignCenterCell)
                    ->addText(
                        _WT($it->get('country')),
                        $this->normalFont,
                        $this->leftParagraph
                    );
                $table->addCell(Converter::cmToTwip(4.50), $this->vAlignCenterCell)
                    ->addText(
                        _WT($it->get('documents')),
                        $this->normalFont,
                        $this->leftParagraph
                    );
            }
        } else {
            $table = $section->addText(
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
    protected function generateTableRecordProcessors($recordId)
    {
        $recordTable = $this->get('recordService')->get('table');
        $recordEntity = $recordTable->getEntity($recordId);
        $processors = $recordEntity->get('processors');

        $tableWord = new PhpWord();
        $section = $tableWord->addSection();
        if (count($processors) < 1) {
            $section->addText(
                $this->anrTranslate('No processor'),
                $this->normalFont,
                $this->leftParagraph
            );
        }

        foreach ($processors as $p) {
            //create section
            $section->addText(
                _WT($p->get('label')),
                $this->boldFont
            );
            $table = $section->addTable($this->borderTable);

            $table->addRow(400);
            $table->addCell(Converter::cmToTwip(4.00), $this->grayCell)
                ->addText(
                    $this->anrTranslate('Name'),
                    $this->boldFont,
                    $this->leftParagraph
                );
            $table->addCell(Converter::cmToTwip(14.00), $this->vAlignCenterCell)
                ->addText(
                    _WT($p->get('label')),
                    $this->normalFont,
                    $this->leftParagraph
                );
            $table->addRow(400);
            $table->addCell(Converter::cmToTwip(4.00), $this->grayCell)
                ->addText(
                    $this->anrTranslate('Contact'),
                    $this->boldFont,
                    $this->leftParagraph
                );
            $table->addCell(Converter::cmToTwip(14.00), $this->vAlignCenterCell)
                ->addText(
                    _WT($p->get('contact')),
                    $this->normalFont,
                    $this->leftParagraph
                );
            $table->addRow(400);
            $table->addCell(Converter::cmToTwip(4.00), $this->grayCell)
                ->addText(
                    $this->anrTranslate('Activities'),
                    $this->boldFont,
                    $this->leftParagraph
                );
            $table->addCell(Converter::cmToTwip(14.00), $this->vAlignCenterCell)
                ->addText(
                    _WT($p->get('activities')),
                    $this->normalFont,
                    $this->leftParagraph
                );
            $table->addRow(400);
            $table->addCell(Converter::cmToTwip(4.00), $this->grayCell)
                ->addText(
                    $this->anrTranslate('Security measures'),
                    $this->boldFont,
                    $this->leftParagraph
                );
            $table->addCell(Converter::cmToTwip(14.00), $this->vAlignCenterCell)
                ->addText(
                    _WT($p->get('secMeasures')),
                    $this->normalFont,
                    $this->leftParagraph
                );

            $section->addTextBreak(1);
            $section->addText(
                $this->anrTranslate('Actors'),
                $this->boldFont
            );
            $tableActor = $section->addTable($this->borderTable);

            $tableActor->addRow(400);
            $tableActor->addCell(Converter::cmToTwip(10.00), $this->grayCell)
                ->addText(
                    $this->anrTranslate('Actor'),
                    $this->boldFont,
                    $this->centerParagraph
                );
            $tableActor->addCell(Converter::cmToTwip(10.00), $this->grayCell)
                ->addText(
                    $this->anrTranslate('Name'),
                    $this->boldFont,
                    $this->centerParagraph
                );
            $tableActor->addCell(Converter::cmToTwip(10.00), $this->grayCell)
                ->addText(
                    $this->anrTranslate('Contact'),
                    $this->boldFont,
                    $this->centerParagraph
                );

            $tableActor->addRow(400);
            $tableActor->addCell(Converter::cmToTwip(10.00), $this->grayCell)
                ->addText(
                    $this->anrTranslate('Representative'),
                    $this->boldFont,
                    $this->leftParagraph
                );
            $tableActor->addCell(Converter::cmToTwip(10.00), $this->vAlignCenterCell)
                ->addText(
                    _WT($p->get('representative') ?
                        $p->get('representative')->get('label') :
                        ""),
                    $this->normalFont,
                    $this->leftParagraph
                );
            $tableActor->addCell(Converter::cmToTwip(10.00), $this->vAlignCenterCell)
                ->addText(
                    _WT($p->get('representative') ?
                        $p->get('representative')->get('contact') :
                        ""),
                    $this->normalFont,
                    $this->leftParagraph
                );

            $tableActor->addRow(400);
            $tableActor->addCell(Converter::cmToTwip(10.00), $this->grayCell)
                ->addText(
                    $this->anrTranslate('Data protection officer'),
                    $this->boldFont,
                    $this->leftParagraph
                );
            $tableActor->addCell(Converter::cmToTwip(10.00), $this->vAlignCenterCell)
                ->addText(
                    _WT($p->get('dpo') ?
                        $p->get('dpo')->get('label') :
                        ""),
                    $this->normalFont,
                    $this->leftParagraph
                );
            $tableActor->addCell(Converter::cmToTwip(10.00), $this->vAlignCenterCell)
                ->addText(
                    _WT($p->get('dpo') ?
                        $p->get('dpo')->get('contact') :
                        ""),
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
    protected function generateTableAllRecordsGDPR()
    {
        $recordTable = $this->get('recordService')->get('table');
        $recordEntities = $recordTable->getEntityByFields(['anr' => $this->anr->getId()]);

        $result = '';

        foreach ($recordEntities as $recordEntity) {
            $tableWord = new PhpWord();
            $section = $tableWord->addSection();
            $tableWord->addTitleStyle(1, $this->titleFont);
            $section->addTitle(
                _WT($recordEntity->get('label')),
                1
            );
            $result .= $this->getWordXmlFromWordObject($tableWord);
            $result .= $this->generateTableRecordGDPR($recordEntity->id);
            //create section
            $tableWord = new PhpWord();
            $section = $tableWord->addSection();
            $tableWord->addTitleStyle(2, $this->titleFont);
            $section->addTitle(
                $this->anrTranslate('Actors'),
                2
            );
            $result .= $this->getWordXmlFromWordObject($tableWord);
            $result .= $this->generateTableRecordActors($recordEntity->id);
            //create section
            $tableWord = new PhpWord();
            $section = $tableWord->addSection();
            $tableWord->addTitleStyle(2, $this->titleFont);
            $section->addTitle(
                $this->anrTranslate('Categories of personal data'),
                2
            );
            $result .= $this->getWordXmlFromWordObject($tableWord);
            $result .= $this->generateTableRecordPersonalData($recordEntity->id);
            //create section
            $tableWord = new PhpWord();
            $section = $tableWord->addSection();
            $tableWord->addTitleStyle(2, $this->titleFont);
            $section->addTitle(
                $this->anrTranslate('Recipients'),
                2
            );
            $result .= $this->getWordXmlFromWordObject($tableWord);
            $result .= $this->generateTableRecordRecipients($recordEntity->id);
            //create section
            $tableWord = new PhpWord();
            $section = $tableWord->addSection();
            $tableWord->addTitleStyle(2, $this->titleFont);
            $section->addTitle(
                $this->anrTranslate('International transfers'),
                2
            );
            $result .= $this->getWordXmlFromWordObject($tableWord);
            $result .= $this->generateTableRecordInternationalTransfers($recordEntity->id);
            //create section
            $tableWord = new PhpWord();
            $section = $tableWord->addSection();
            $tableWord->addTitleStyle(2, $this->titleFont);
            $section->addTitle(
                $this->anrTranslate('Processors'),
                2
            );
            $result .= $this->getWordXmlFromWordObject($tableWord);
            $result .= $this->generateTableRecordProcessors($recordEntity->id);
        }

        return $result;
    }

    /**
     * Generate the impacts appreciation table data
     * @return mixed|string The WordXml table data
     */
    protected function generateImpactsAppreciation()
    {
        // TODO: C'est moche, optimiser
        $allInstances = $this->instanceTable->findByAnrAndOrderByParams($this->anr, ['i.position' => 'ASC']);
        /** @var Instance[] $instances */
        $instances = array_filter($allInstances, function ($ins) {
            return ($ins->getConfidentiality() > -1 && !$ins->isConfidentialityInherited()) ||
                ($ins->getIntegrity() > -1 && !$ins->isIntegrityInherited()) ||
                ($ins->getAvailability() > -1 && !$ins->getAvailabilityInherited());
        });
        $impacts = ['c', 'i', 'd'];
        $instanceCriteria = Instance::getAvailableScalesCriteria();

        $tableWord = new PhpWord();
        $section = $tableWord->addSection();
        $table = $section->addTable($this->borderTable);

        //header
        if (\count($instances)) {
            $table->addRow(400, $this->tblHeader);
            $table->addCell(Converter::cmToTwip(9.00), $this->setColSpanCell(3, 'DFDFDF'))
                ->addText(
                    $this->anrTranslate('Impact'),
                    $this->boldFont,
                    $this->centerParagraph
                );
            $table->addCell(Converter::cmToTwip(9.00), $this->setColSpanCell(3, 'DFDFDF'))
                ->addText(
                    $this->anrTranslate('Consequences'),
                    $this->boldFont,
                    $this->centerParagraph
                );
        }

        $globalObjectsUuids = [];
        foreach ($instances as $instance) {
            /* Check if the global object is already added. */
            if (\in_array($instance->getObject()->getUuid(), $globalObjectsUuids, true)) {
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
                            $table->addCell(Converter::cmToTwip(16), $this->setColSpanCell(6, 'DBE5F1'))
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
                            ];
                            $translatedImpact = ucfirst($impact);
                            if ($impact === 'd') {
                                $translatedImpact = ucfirst($this->anrTranslate('A'));
                            }
                            $table->addCell(Converter::cmToTwip(1.00), $this->restartAndCenterCell)
                                ->addText(
                                    $translatedImpact,
                                    $this->boldFont,
                                    $this->centerParagraph
                                );
                            $table->addCell(Converter::cmToTwip(1.00), $this->restartAndCenterCell)
                                ->addText(
                                    $instance->{'get' . $instanceCriteria[$impact]}(),
                                    $this->boldFont,
                                    $this->centerParagraph
                                );
                            $table->addCell(Converter::cmToTwip(5.00), $this->restartAndCenterCell)
                                ->addText(
                                    _WT($comment),
                                    $this->normalFont,
                                    $this->leftParagraph
                                );
                        } else {
                            $table->addCell(Converter::cmToTwip(1.00), $this->continueCell);
                            $table->addCell(Converter::cmToTwip(1.00), $this->continueCell);
                            $table->addCell(Converter::cmToTwip(5.00), $this->continueCell);
                        }
                        $comment = $instanceConsequence['comments'][
                            $instanceConsequence[$impact . '_risk'] !== -1 ? $instanceConsequence[$impact . '_risk'] : 0
                        ];
                        $table->addCell(Converter::cmToTwip(1.00), $this->vAlignCenterCell)
                            ->addText(
                                _WT($instanceConsequence['scaleImpactTypeDescription' . $this->currentLangAnrIndex]),
                                $this->boldFont,
                                $this->centerParagraph
                            );
                        $table->addCell(Converter::cmToTwip(1.00), $this->vAlignCenterCell)
                            ->addText(
                                $instanceConsequence[$impact . '_risk'],
                                $this->boldFont,
                                $this->centerParagraph
                            );
                        $table->addCell(Converter::cmToTwip(7.00), $this->vAlignCenterCell)
                            ->addText(
                                _WT($comment),
                                $this->normalFont,
                                $this->leftParagraph
                            );

                        $headerConsequence = true;
                    }
                }
            }
        }

        return $table;
    }

    /**
     * Generate the threats table data
     * @param bool $fullGen Whether or not to generate the full table (all but normal) or just the normal threats
     * @return mixed|string The WordXml generated data
     */
    protected function generateThreatsTable($fullGen = false)
    {
        // TODO: use threatTable instead.
        $threats = $this->threatService->getList(1, 0, null, null, ['anr' => $this->anr->getId()]);

        $tableWord = new PhpWord();
        $section = $tableWord->addSection();
        $table = $section->addTable($this->borderTable);

        $nbThreats = 0;
        foreach ($threats as $threat) {
            if (($threat['trend'] != 1) || $fullGen) {
                $nbThreats++;
            }
        }

        if ($nbThreats > 0) {
            $table->addRow(400, $this->tblHeader);
            $table->addCell(Converter::cmToTwip(7.60), $this->grayCell)
                ->addText(
                    $this->anrTranslate('Threat'),
                    $this->boldFont,
                    $this->centerParagraph
                );
            $table->addCell(Converter::cmToTwip(1.50), $this->grayCell)
                ->addText(
                    $this->anrTranslate('CIA'),
                    $this->boldFont,
                    $this->centerParagraph
                );
            $table->addCell(Converter::cmToTwip(1.70), $this->grayCell)
                ->addText(
                    $this->anrTranslate('Tend.'),
                    $this->boldFont,
                    $this->centerParagraph
                );
            $table->addCell(Converter::cmToTwip(1.60), $this->grayCell)
                ->addText(
                    $this->anrTranslate('Prob.'),
                    $this->boldFont,
                    $this->centerParagraph
                );
            $table->addCell(Converter::cmToTwip(6.60), $this->grayCell)
                ->addText(
                    $this->anrTranslate('Comment'),
                    $this->boldFont,
                    $this->centerParagraph
                );
        }

        foreach ($threats as $threat) {
            if (($threat['trend'] != 1) || $fullGen) { // All but normal
                $table->addRow(400);
                $table->addCell(Converter::cmToTwip(5.85), $this->vAlignCenterCell)
                    ->addText(
                        _WT($threat['label' . $this->currentLangAnrIndex]),
                        $this->normalFont,
                        $this->leftParagraph
                    );

                // CID
                $cid = '';
                if ($threat['c']) {
                    $cid .= 'C';
                }
                if ($threat['i']) {
                    $cid .= 'I';
                }
                if ($threat['a']) {
                    $cid .= $this->anrTranslate('A');
                }
                $table->addCell(Converter::cmToTwip(1.50), $this->vAlignCenterCell)
                    ->addText(
                        $cid,
                        $this->normalFont,
                        $this->centerParagraph
                    );

                // Trend
                switch ($threat['trend']) {
                    case 0:
                        $trend = '-';
                        break;
                    case 1:
                        $trend = 'n';
                        break;
                    case 2:
                        $trend = '+';
                        break;
                    case 3:
                        $trend = '++';
                        break;
                    default:
                        $trend = '';
                        break;
                }
                $table->addCell(Converter::cmToTwip(1.70), $this->vAlignCenterCell)
                    ->addText(
                        $trend,
                        $this->normalFont,
                        $this->centerParagraph
                    );

                // Pre-Q
                $qual = $threat['qualification'] >= 0 ? $threat['qualification'] : '';
                $table->addCell(Converter::cmToTwip(1.60), $this->vAlignCenterCell)
                    ->addText(
                        $qual,
                        $this->normalFont,
                        $this->centerParagraph
                    );
                $table->addCell(Converter::cmToTwip(6.60), $this->vAlignCenterCell)
                    ->addText(
                        _WT($threat['comment']),
                        $this->normalFont,
                        $this->leftParagraph
                    );
            }
        }

        return $table;
    }

    /**
     * Generate the owner table data
     * @return mixed|string The WordXml generated data
     */
    protected function generateOwnersTable()
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

                    if (\in_array($uniqueKey, $globalObjectsUuids, true)) {
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

        $tableWord = new PhpWord();
        $section = $tableWord->addSection();
        $table = $section->addTable($this->borderTable);

        if (!empty($risksByOwner)) {
            $table->addRow(400, $this->tblHeader);
            $table->addCell(Converter::cmToTwip(2.00), $this->grayCell)
                ->addText(
                    $this->anrTranslate('Owner'),
                    $this->boldFont,
                    $this->centerParagraph
                );
            $table->addCell(Converter::cmToTwip(6.00), $this->grayCell)
                ->addText(
                    $this->anrTranslate('Asset'),
                    $this->boldFont,
                    $this->centerParagraph
                );
            $table->addCell(Converter::cmToTwip(10.00), $this->setColSpanCell(2, 'DFDFDF'))
                ->addText(
                    $this->anrTranslate('Risk'),
                    $this->boldFont,
                    $this->centerParagraph
                );
            foreach ($risksByOwner as $owner => $risks) {
                $isOwnerHeader = true;
                foreach ($risks as $risk) {
                    $table->addRow(400);
                    if ($isOwnerHeader) {
                        $table->addCell(Converter::cmToTwip(2.00), $this->restartAndCenterCell)
                            ->addText(
                                _WT($owner),
                                $this->boldFont,
                                $this->leftParagraph
                            );
                    } else {
                        $table->addCell(Converter::cmToTwip(2.00), $this->continueCell);
                    }
                    $table->addCell(Converter::cmToTwip(6.00), $this->vAlignCenterCell)
                        ->addText(
                            _WT($risk['asset']),
                            $this->normalFont,
                            $this->leftParagraph
                        );
                    if (isset($risk['threat'])) {
                        $table->addCell(Converter::cmToTwip(3.00), $this->vAlignCenterCell)
                            ->addText(
                                _WT($risk['threat']),
                                $this->normalFont,
                                $this->leftParagraph
                            );
                        $table->addCell(Converter::cmToTwip(7.00), $this->vAlignCenterCell)
                            ->addText(
                                _WT($risk['vulnerability']),
                                $this->normalFont,
                                $this->leftParagraph
                            );
                    } else {
                        $table->addCell(Converter::cmToTwip(10.00), $this->setColSpanCell(2))
                            ->addText(
                                _WT($risk['risk']),
                                $this->normalFont,
                                $this->leftParagraph
                            );
                    }
                    $isOwnerHeader = false;
                }
            }
        }

        return $table;
    }

    /**
     * Generate the asset context table data
     * @return mixed|string The WordXml generated data
     */
    protected function generateAssetContextTable()
    {
        $allInstances = $this->instanceTable->findByAnr($this->anr);
        $allMetadatas = $this->metadatasOnInstancesTable->findByAnr($this->anr);
        $translations = $this->translationTable->findByAnrTypesAndLanguageIndexedByKey(
            $this->anr,
            [Translation::INSTANCE_METADATA, Translation::ANR_METADATAS_ON_INSTANCES],
            $this->configService->getActiveLanguageCodes()[$this->anr->getLanguage()]
        );
        $assetUuids = [];
        foreach ($allMetadatas as $metadata) {
            $translationLabel = $translations[$metadata->getLabelTranslationKey()] ?? null;
            $headersMetadata[] = $translationLabel !== null ? $translationLabel->getValue() : '';
        }
        if (!isset($headersMetadata)) {
            return;
        }
        $sizeColumn = 13 / count($headersMetadata);

        $tableWord = new PhpWord();
        $section = $tableWord->addSection();
        $tableWord->addTitleStyle(3, $this->titleFont);

        foreach ($allInstances as $instance) {
            $assetUuid = $instance->getAsset()->getUuid();
            if (\in_array($assetUuid, $assetUuids, true)) {
                continue;
            }
            $assetUuids[] = $assetUuid;
            $typeAsset = ($instance->getAsset()->getType() == 1) ? 'PrimaryAssets' : 'SecondaryAssets';
            $assetLabel = $instance->{'getName' . $this->currentLangAnrIndex}();
            if ($instance->getObject()->isScopeGlobal()) {
                $assetLabel = $assetLabel . ' (' . $this->anrTranslate('Global') . ')';
            }
            $instanceMetadatas = $instance->getInstanceMetadatas();

            if (!isset(${'table' . $typeAsset})) {
                $section->addTitle(
                    $this->anrTranslate(
                        ($instance->getAsset()->getType() == 1) ?
                            'Primary assets' :
                            'Secondary assets'
                    ),
                    3
                );
                ${'table' . $typeAsset} = $section->addTable($this->borderTable);
                ${'table' . $typeAsset}->addRow(400, $this->tblHeader);
                ${'table' . $typeAsset}->addCell(Converter::cmToTwip(4.00), $this->grayCell)
                    ->addText(
                        $this->anrTranslate('Asset'),
                        $this->boldFont,
                        $this->centerParagraph
                    );
                foreach ($headersMetadata as $headerMetadata) {
                    ${'table' . $typeAsset}->addCell(Converter::cmToTwip($sizeColumn), $this->grayCell)
                        ->addText(
                            _WT($headerMetadata),
                            $this->boldFont,
                            $this->centerParagraph
                        );
                }
            }

            ${'table' . $typeAsset}->addRow(400);
            ${'table' . $typeAsset}->addCell(Converter::cmToTwip(4.00), $this->vAlignCenterCell)
                ->addText(
                    _WT($assetLabel),
                    $this->normalFont,
                    $this->leftParagraph
                );

            foreach ($allMetadatas as $metadata) {
                if ($instanceMetadatas) {
                    $metadataFiltered = array_filter(
                        $instanceMetadatas->toArray(),
                        function ($im) use ($metadata) {
                            return  $metadata->getId() == $im->getMetadata()->getId();
                        }
                    );
                }
                $translationComment = null;

                if (count($metadataFiltered) > 0) {
                    $translationComment = $translations[reset($metadataFiltered)->getCommentTranslationKey()] ?? null;
                }

                ${'table' . $typeAsset}->addCell(Converter::cmToTwip($sizeColumn), $this->vAlignCenterCell)
                    ->addText(
                        $translationComment !== null ? _WT($translationComment->getValue()) : '',
                        $this->normalFont,
                        $this->leftParagraph
                    );
            }
        }

        return $this->getWordXmlFromWordObject($tableWord);
    }

    /**
     * Retrieves the label of kindOfMeasure
     * @param int $kindOfMeasure value
     * @return string  kindOfMeasure label
     */
    private function getKindfofMeasureLabel($kindOfMeasure)
    {
        switch ($kindOfMeasure) {
            case 1:
                $kindfofMeasureLabel = "Reduction";
                break;
            case 2:
                $kindfofMeasureLabel = "Denied";
                break;
            case 3:
                $kindfofMeasureLabel = "Accepted";
                break;
            case 4:
                $kindfofMeasureLabel = "Shared";
                break;
            default:
                $kindfofMeasureLabel = "Not treated";
        }

        return $this->anrTranslate($kindfofMeasureLabel);
    }

    /**
     * Retrieves the company name to display within the document
     * @return string The company name
     */
    public function getCompanyName()
    {
        return $this->clientTable->findFirstClient()->getName();
    }

    /**
     * Generates WordXml data from HTML.
     * @param string $input HTML input
     * @return string WordXml data
     */
    protected function generateWordXmlFromHtml($input)
    {
        // Process trix caveats
        $input = html_entity_decode($input);
        $input = str_replace(
            ['&lt;', '&gt;', '&amp;','<br>'],
            ['[escape_lt]', '[escape_gt]', '[escape_amp]','<!--block-->'],
            $input
        );

        while (strpos($input, '<ul>') !== false) {
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

        while (strpos($input, '<ol>') !== false) {
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
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        Html::addHtml($section, $input);

        return $this->getWordXmlFromWordObject($phpWord);
    }

    /**
     * Generates the instances tree
     * @param array $elements instances risks array
     * @param int $parentId id of parent_Root
     *
     * @return array
     */
    protected function buildTree($elements, $parentId)
    {
        $branch = [];
        foreach ($elements as $element => $value) {
            if ($value['parent'] == $parentId) {
                $children = $this->buildTree($elements, $element);
                if ($children) {
                    usort($children, function ($a, $b) {
                        return $a['position'] <=> $b['position'];
                    });
                    $value['children'] = $children;
                }
                $branch[] = $value;
            } elseif (!isset($value['parent']) && $parentId == $element) {
                $branch[] = $value;
            }
        }
        usort($branch, function ($a, $b) {
            return $a['position'] <=> $b['position'];
        });

        return $branch;
    }

    /**
     * Generates a single-level array from multilevel array
     * @param array $multiLevelArray
     * @return array
     */
    protected function singleLevelArray($multiLevelArray)
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
     * @param PhpWord $phpWord The PhpWord Object
     * @return string The WordXml data
     */
    protected function getWordXmlFromWordObject($phpWord)
    {
        $part = new Document();
        $part->setParentWriter(new Word2007($phpWord));
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

    private function getObjectInstancePath(RecommandationRisk $recommendationRisk): string
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

function _WT($input)
{
    $input = htmlspecialchars(trim($input), ENT_COMPAT, 'UTF-8');
    return str_replace("\n", '</w:t><w:br/><w:t>', $input);
}
