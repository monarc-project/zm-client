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
use ZipArchive;
use function array_diff;
use function array_key_exists;
use function array_map;
use function basename;
use function count;
use function escapeshellarg;
use function exec;
use function file_exists;
use function html_entity_decode;
use function htmlspecialchars;
use function implode;
use function is_dir;
use function mkdir;
use function pathinfo;
use function preg_match;
use function preg_replace;
use function preg_split;
use function rawurlencode;
use function rmdir;
use function scandir;
use function strip_tags;
use function str_replace;
use function strtolower;
use function sys_get_temp_dir;
use function tempnam;
use function trim;
use function unlink;

/**
 * The service handles generation of the deliverable Word documents throughout the steps of risk analysis.
 */
class DeliverableGenerationService
{
    private const OUTPUT_FORMAT_DOCX = 'docx';
    private const OUTPUT_FORMAT_PDF = 'pdf';
    private const DOCX_CONTENT_TYPE = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
    private const PDF_CONTENT_TYPE = 'application/pdf';
    private const PDF_COVER_DEFAULT_SUBJECT = 'Information security - MONARC';

    private const VERSION = 'VERSION';
    private const STATE = 'STATE';
    private const CLASSIFICATION = 'CLASSIFICATION';
    private const COMPANY = 'COMPANY';
    private const DOCUMENT = 'DOCUMENT';
    private const DATE = 'DATE';
    private const CLIENT = 'CLIENT';
    private const SMILE = 'SMILE';
    private const SUMMARY_EVAL_RISK = 'SUMMARY_EVAL_RISK';
    private const CONTEXT_ANA_RISK = 'CONTEXT_ANA_RISK';
    private const CONTEXT_GEST_RISK = 'CONTEXT_GEST_RISK';
    private const SYNTH_EVAL_THREAT = 'SYNTH_EVAL_THREAT';
    private const SYNTH_ACTIF = 'SYNTH_ACTIF';
    private const SCALE_IMPACT = 'SCALE_IMPACT';
    private const SCALE_THREAT = 'SCALE_THREAT';
    private const SCALE_VULN = 'SCALE_VULN';
    private const TABLE_RISKS = 'TABLE_RISKS';
    private const OP_RISKS_SCALE_IMPACT = 'OP_RISKS_SCALE_IMPACT';
    private const OP_RISKS_SCALE_LIKELIHOOD = 'OP_RISKS_SCALE_LIKELIHOOD';
    private const TABLE_OP_RISKS = 'TABLE_OP_RISKS';
    private const TABLE_THREATS = 'TABLE_THREATS';
    private const TABLE_EVAL_TEND = 'TABLE_EVAL_TEND';
    private const TABLE_THREATS_FULL = 'TABLE_THREATS_FULL';
    private const TABLE_INTERVIEW = 'TABLE_INTERVIEW';
    private const TABLE_INTERESTED_PARTIES = 'TABLE_INTERESTED_PARTIES';
    private const TABLE_REASSESSMENT_TRIGGERS = 'TABLE_REASSESSMENT_TRIGGERS';
    private const IMPACTS_APPRECIATION = 'IMPACTS_APPRECIATION';
    private const GRAPH_EVAL_RISK = 'GRAPH_EVAL_RISK';
    private const GRAPH_EVAL_OP_RISK = 'GRAPH_EVAL_OP_RISK';
    private const RISKS_RECO_FULL = 'RISKS_RECO_FULL';
    private const OPRISKS_RECO_FULL = 'OPRISKS_RECO_FULL';
    private const TABLE_RISK_OWNERS = 'TABLE_RISK_OWNERS';
    private const DISTRIB_EVAL_RISK = 'DISTRIB_EVAL_RISK';
    private const DISTRIB_EVAL_OP_RISK = 'DISTRIB_EVAL_OP_RISK';
    private const CURRENT_RISK_MAP = 'CURRENT_RISK_MAP';
    private const TARGET_RISK_MAP = 'TARGET_RISK_MAP';
    private const TABLE_ASSET_CONTEXT = 'TABLE_ASSET_CONTEXT';
    private const RISKS_KIND_OF_TREATMENT = 'RISKS_KIND_OF_TREATMENT';
    private const TABLE_AUDIT_INSTANCES = 'TABLE_AUDIT_INSTANCES';
    private const TABLE_AUDIT_RISKS_OP = 'TABLE_AUDIT_RISKS_OP';
    private const TABLE_IMPLEMENTATION_PLAN = 'TABLE_IMPLEMENTATION_PLAN';
    private const TABLE_IMPLEMENTATION_HISTORY = 'TABLE_IMPLEMENTATION_HISTORY';
    private const TABLE_STATEMENT_OF_APPLICABILITY_SCALE = 'TABLE_STATEMENT_OF_APPLICABILITY_SCALE';
    private const TABLE_STATEMENT_OF_APPLICABILITY = 'TABLE_STATEMENT_OF_APPLICABILITY';
    private const TABLE_RISKS_BY_CONTROL = 'TABLE_RISKS_BY_CONTROL';
    private const TABLE_RECORD_INFORMATION = 'TABLE_RECORD_INFORMATION';
    private const TABLE_RECORD_ACTORS = 'TABLE_RECORD_ACTORS';
    private const TABLE_RECORD_PERSONAL_DATA = 'TABLE_RECORD_PERSONAL_DATA';
    private const TABLE_RECORD_RECIPIENTS = 'TABLE_RECORD_RECIPIENTS';
    private const TABLE_RECORD_INTERNATIONAL_TRANSFERS = 'TABLE_RECORD_INTERNATIONAL_TRANSFERS';
    private const TABLE_RECORD_PROCESSORS = 'TABLE_RECORD_PROCESSORS';
    private const TABLE_ALL_RECORDS = 'TABLE_ALL_RECORDS';

    private UserSuperClass $connectedUser;

    private int $currentLangAnrIndex = 1;

    private ?Entity\Anr $anr;

    private string $configuredPdfConverterBinary;
    private string $currentOutputFormat = self::OUTPUT_FORMAT_DOCX;

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
        private Table\AnrSupervisorTable $anrSupervisorTable,
        private Table\ReassessmentTriggerTable $reassessmentTriggerTable,
        private Table\InterestedPartyTable $interestedPartyTable,
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
        CoreService\ConnectedUserService $connectedUserService,
        array $config
    ) {
        $this->connectedUser = $connectedUserService->getConnectedUser();
        $this->configuredPdfConverterBinary = (string)($config['deliverable']['pdfConverterBinary'] ?? '/usr/bin/soffice');
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
     * @return array{path: string, extension: string, contentType: string} The output file metadata.
     */
    public function generateDeliverableWithValues(Entity\Anr $anr, int $docType, array $data): array
    {
        $outputFormat = $this->normalizeOutputFormat($data['format'] ?? null);
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
        $this->currentOutputFormat = $outputFormat;
        $this->setStyles();

        $deliveryModel = $this->deliveriesModelsTable->findById((int)$data['template']);

        $values = [
            'txt' => [
                self::VERSION => htmlspecialchars($delivery->getVersion()),
                self::STATE => $delivery->getStatus() === 0 ? 'Draft' : 'Final',
                self::CLASSIFICATION => htmlspecialchars($delivery->getClassification()),
                self::COMPANY => htmlspecialchars($this->clientTable->findFirstClient()->getName()),
                self::DOCUMENT => htmlspecialchars($delivery->getName()),
                self::DATE => date('d/m/Y'),
                self::CLIENT => htmlspecialchars($delivery->getResponsibleManager()),
                self::SMILE => htmlspecialchars($delivery->getRespCustomer()),
            ],
            'xml' => [
                self::SUMMARY_EVAL_RISK => $this->generateWordXmlFromPlainText(
                    $this->convertRichTextToPlainText($delivery->getSummaryEvalRisk()),
                    ['spaceAfter' => 120],
                    ['spaceBefore' => 240, 'spaceAfter' => 120]
                ),
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

        if ($outputFormat === self::OUTPUT_FORMAT_PDF) {
            $values = $this->normalizeValuesForPdf($values);
        }

        $wordFilePath = $this->generateDeliverableWithValuesAndModel($pathModel, $values);
        $this->finalizeGeneratedWordDocument($wordFilePath, $outputFormat === self::OUTPUT_FORMAT_PDF);

        if ($outputFormat === self::OUTPUT_FORMAT_PDF) {
            return $this->convertWordDocumentToPdf($wordFilePath);
        }

        return [
            'path' => $wordFilePath,
            'extension' => self::OUTPUT_FORMAT_DOCX,
            'contentType' => self::DOCX_CONTENT_TYPE,
        ];
    }

    private function normalizeValuesForPdf(array $values): array
    {
        $values['xml'][self::DISTRIB_EVAL_RISK] = $this->generateWordXmlFromPlainText(
            $this->convertRichTextToPlainText($this->getRisksDistribution())
        );

        $values['xml'][self::DISTRIB_EVAL_OP_RISK] = $this->generateWordXmlFromPlainText(
            $this->convertRichTextToPlainText($this->getRisksDistribution(false))
        );

        return $values;
    }

    private function finalizeGeneratedWordDocument(string $documentPath, bool $optimizeForPdf): void
    {
        $zipArchive = new ZipArchive();

        if ($zipArchive->open($documentPath) !== true) {
            throw new Exception('The generated deliverable could not be prepared.');
        }

        $documentXml = $zipArchive->getFromName('word/document.xml');
        if ($documentXml === false) {
            $zipArchive->close();
            throw new Exception('The generated deliverable is missing its main document part.');
        }

        if ($optimizeForPdf) {
            $documentXml = $this->normalizePdfCoverPage($documentXml);
        }

        $zipArchive->addFromString('word/document.xml', $documentXml);
        $zipArchive->close();
    }

    private function normalizePdfCoverPage(string $documentXml): string
    {
        return preg_replace_callback(
            '/(<w:body>)(.*?<w:br w:type="page"\/>.*?<\/w:p>)/s',
            function (array $matches): string {
                $coverXml = $this->flattenPdfCoverTitleField($matches[2]);
                $coverXml = $this->convertPdfCoverLogoToInline($coverXml);
                $coverXml = preg_replace('/<w:ind\b[^>]*\/>/s', '', $coverXml) ?? $coverXml;

                return $matches[1] . $coverXml;
            },
            $documentXml,
            1
        ) ?? $documentXml;
    }

    private function flattenPdfCoverTitleField(string $coverXml): string
    {
        $coverXml = preg_replace_callback(
            '/<w:r\b[^>]*>\s*(<w:rPr>.*?<\/w:rPr>)?\s*<w:fldChar w:fldCharType="begin"\/>\s*<\/w:r>\s*'
            . '<w:r\b[^>]*>\s*(?:<w:rPr>.*?<\/w:rPr>)?\s*<w:instrText[^>]*>\s*DOCPROPERTY\s+Subject\s+\\\\\* MERGEFORMAT\s*<\/w:instrText>\s*<\/w:r>\s*'
            . '<w:r\b[^>]*>\s*(?:<w:rPr>.*?<\/w:rPr>)?\s*<w:fldChar w:fldCharType="separate"\/>\s*<\/w:r>.*?'
            . '<w:r\b[^>]*>\s*(?:<w:rPr>.*?<\/w:rPr>)?\s*<w:fldChar w:fldCharType="end"\/>\s*<\/w:r>/s',
            function (array $matches): string {
                $runProperties = $matches[1] ?? '';
                $coverTitle = $this->extractWordFieldDisplayText($matches[0]);
                if ($coverTitle === '') {
                    $coverTitle = self::PDF_COVER_DEFAULT_SUBJECT;
                }

                return '<w:r>' . $runProperties . '<w:t xml:space="preserve">'
                    . htmlspecialchars($coverTitle, ENT_COMPAT | ENT_XML1)
                    . '</w:t></w:r>';
            },
            $coverXml,
            1
        ) ?? $coverXml;

        return $coverXml;
    }

    private function extractWordFieldDisplayText(string $xml): string
    {
        $separatorPosition = strpos($xml, '<w:fldChar w:fldCharType="separate"/>');
        $endPosition = strpos($xml, '<w:fldChar w:fldCharType="end"/>');

        if ($separatorPosition === false || $endPosition === false || $endPosition <= $separatorPosition) {
            return '';
        }

        return $this->extractWordTextFromXml(substr($xml, $separatorPosition, $endPosition - $separatorPosition));
    }

    private function extractWordTextFromXml(string $xml): string
    {
        if (preg_match_all('/<w:t\b[^>]*>(.*?)<\/w:t>/s', $xml, $matches) < 1) {
            return '';
        }

        return trim(html_entity_decode(implode('', $matches[1]), ENT_COMPAT | ENT_XML1));
    }

    private function convertPdfCoverLogoToInline(string $coverXml): string
    {
        $coverXml = preg_replace(
            '/<wp:anchor\b[^>]*>/',
            '<wp:inline distT="0" distB="0" distL="0" distR="0">',
            $coverXml,
            1
        ) ?? $coverXml;

        $coverXml = preg_replace('/<wp:simplePos\b[^>]*\/>/s', '', $coverXml, 1) ?? $coverXml;
        $coverXml = preg_replace('/<wp:positionH\b[^>]*>.*?<\/wp:positionH>/s', '', $coverXml, 1) ?? $coverXml;
        $coverXml = preg_replace('/<wp:positionV\b[^>]*>.*?<\/wp:positionV>/s', '', $coverXml, 1) ?? $coverXml;
        $coverXml = preg_replace('/<wp:wrapSquare\b[^>]*\/>/s', '', $coverXml, 1) ?? $coverXml;
        $coverXml = preg_replace('/<wp14:sizeRelH\b[^>]*>.*?<\/wp14:sizeRelH>/s', '', $coverXml, 1) ?? $coverXml;
        $coverXml = preg_replace('/<wp14:sizeRelV\b[^>]*>.*?<\/wp14:sizeRelV>/s', '', $coverXml, 1) ?? $coverXml;
        $coverXml = preg_replace('/<\/wp:anchor>/', '</wp:inline>', $coverXml, 1) ?? $coverXml;

        return $coverXml;
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

        $temporaryDocumentPath = $this->createTemporaryFilePath('monarc-deliverable-', '.docx');
        $word->saveAs($temporaryDocumentPath);

        return $temporaryDocumentPath;
    }

    private function normalizeOutputFormat(mixed $outputFormat): string
    {
        $outputFormat = strtolower((string)$outputFormat);

        return $outputFormat === self::OUTPUT_FORMAT_PDF ? self::OUTPUT_FORMAT_PDF : self::OUTPUT_FORMAT_DOCX;
    }

    /**
     * @return array{path: string, extension: string, contentType: string}
     */
    private function convertWordDocumentToPdf(string $wordFilePath): array
    {
        if ($this->configuredPdfConverterBinary === '' || !file_exists($this->configuredPdfConverterBinary)) {
            throw new Exception('The PDF converter binary could not be found.');
        }

        $outputDirectory = sys_get_temp_dir();
        $profileDirectory = $this->createTemporaryDirectory('monarc-lo-profile-');
        $homeDirectory = $this->createTemporaryDirectory('monarc-lo-home-');
        $cacheDirectory = $homeDirectory . '/.cache';
        $pdfFilePath = $outputDirectory . '/' . pathinfo($wordFilePath, PATHINFO_FILENAME) . '.pdf';

        if (!mkdir($cacheDirectory, 0700) && !is_dir($cacheDirectory)) {
            throw new Exception('The PDF converter cache directory could not be created.');
        }

        $command = implode(' ', [
            'env',
            'HOME=' . escapeshellarg($homeDirectory),
            'XDG_CACHE_HOME=' . escapeshellarg($cacheDirectory),
            'SAL_USE_VCLPLUGIN=svp',
            escapeshellarg($this->configuredPdfConverterBinary),
            '--headless',
            '--nologo',
            '--nodefault',
            '--nolockcheck',
            '--norestore',
            '-env:UserInstallation=' . escapeshellarg($this->convertPathToFileUri($profileDirectory)),
            '--convert-to',
            escapeshellarg('pdf:writer_pdf_Export'),
            '--outdir',
            escapeshellarg($outputDirectory),
            escapeshellarg($wordFilePath),
            '2>&1',
        ]);

        $commandOutput = [];
        $exitCode = 0;

        try {
            exec($command, $commandOutput, $exitCode);
        } finally {
            if (file_exists($wordFilePath)) {
                unlink($wordFilePath);
            }
            $this->removeDirectoryRecursively($profileDirectory);
            $this->removeDirectoryRecursively($homeDirectory);
        }

        if ($exitCode !== 0 || !file_exists($pdfFilePath)) {
            throw new Exception('The PDF conversion failed: ' . implode("\n", $commandOutput));
        }

        return [
            'path' => $pdfFilePath,
            'extension' => self::OUTPUT_FORMAT_PDF,
            'contentType' => self::PDF_CONTENT_TYPE,
        ];
    }

    private function createTemporaryDirectory(string $prefix): string
    {
        $temporaryPath = tempnam(sys_get_temp_dir(), $prefix);

        if ($temporaryPath === false) {
            throw new Exception('A temporary directory for the PDF conversion could not be created.');
        }

        if (file_exists($temporaryPath)) {
            unlink($temporaryPath);
        }

        if (!mkdir($temporaryPath, 0700) && !is_dir($temporaryPath)) {
            throw new Exception('A temporary directory for the PDF conversion could not be created.');
        }

        return $temporaryPath;
    }

    private function createTemporaryFilePath(string $prefix, string $extension): string
    {
        $temporaryPath = tempnam(sys_get_temp_dir(), $prefix);

        if ($temporaryPath === false) {
            throw new Exception('A temporary file for the deliverable could not be created.');
        }

        if (file_exists($temporaryPath)) {
            unlink($temporaryPath);
        }

        return $temporaryPath . $extension;
    }

    private function convertPathToFileUri(string $path): string
    {
        $normalizedPath = str_replace('\\', '/', $path);

        return 'file://' . implode('/', array_map('rawurlencode', explode('/', $normalizedPath)));
    }

    private function removeDirectoryRecursively(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $entries = scandir($directory);
        if ($entries === false) {
            return;
        }

        foreach (array_diff($entries, ['.', '..']) as $entry) {
            $entryPath = $directory . '/' . $entry;

            if (is_dir($entryPath)) {
                $this->removeDirectoryRecursively($entryPath);
                continue;
            }

            unlink($entryPath);
        }

        rmdir($directory);
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
            $backgroundColor = 'E7E6E6';
        } else {
            $backgroundColor = 'FD661F';
            if ($this->isUnavailableDisplayValue($value) || $value === '-') {
                $backgroundColor = 'FFFFFF';
            } elseif ($value <= $thresholds['low']) {
                $backgroundColor = 'D6F107';
            } elseif ($value <= $thresholds['high']) {
                $backgroundColor = 'FFBC1C';
            }
        }

        $this->customizableCell['bgcolor'] = $backgroundColor;
        $this->customizableCell['BgColor'] = $backgroundColor;

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
                self::CONTEXT_ANA_RISK => $this->generateWordXmlFromHtml(_WT($this->anr->getContextAnaRisk())),
                self::CONTEXT_GEST_RISK => $this->generateWordXmlFromHtml(_WT($this->anr->getContextGestRisk())),
                self::SYNTH_EVAL_THREAT => $this->generateWordXmlFromHtml(_WT($this->anr->getSynthThreat())),
            ],
            'table' => [
                self::SCALE_IMPACT => $this->generateInformationalRiskImpactsTable($impactsScale),
                self::SCALE_THREAT => $this->generateThreatOrVulnerabilityScaleTable($threatsScale),
                self::SCALE_VULN => $this->generateThreatOrVulnerabilityScaleTable($vulnsScale),
                self::TABLE_RISKS => $this->generateInformationalRiskAcceptanceThresholdsTable(
                    $impactsScale,
                    $threatsScale,
                    $vulnsScale
                ),
                self::OP_RISKS_SCALE_IMPACT => $this->generateOperationalRiskImpactsTable(
                    $opRisksImpactsScales,
                    $opRisksImpactsScaleMin,
                    $opRisksImpactsScaleMax
                ),
                self::OP_RISKS_SCALE_LIKELIHOOD => $this->generateOperationalRiskLikelihoodTable(
                    $opRisksLikelihoodScale
                ),
                self::TABLE_OP_RISKS => $this->generateOperationalRiskAcceptanceThresholdsTable(
                    $opRisksImpactsScales,
                    $opRisksLikelihoodScale,
                    $opRisksImpactsScaleMin,
                    $opRisksImpactsScaleMax
                ),
                self::TABLE_THREATS => $this->generateThreatsTable(),
                self::TABLE_EVAL_TEND => $this->generateTrendAssessmentTable(),
                self::TABLE_THREATS_FULL => $this->generateThreatsTable(true),
                self::TABLE_INTERVIEW => $this->generateInterviewsTable(),
                self::TABLE_INTERESTED_PARTIES => $this->generateInterestedPartiesTable(),
                self::TABLE_REASSESSMENT_TRIGGERS => $this->generateReassessmentTriggersTable(),
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

        $values['xml'][self::SYNTH_ACTIF] = $this->generateWordXmlFromHtml(_WT($this->anr->getSynthAct()));
        $values['table'][self::IMPACTS_APPRECIATION] = $this->generateImpactsAppreciation();

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
                self::GRAPH_EVAL_RISK => $this->generateRisksGraph(),
                self::GRAPH_EVAL_OP_RISK => $this->generateRisksGraph(false),
            ]
        ]);

        $values = array_merge_recursive($values, [
            'table' => [
                self::RISKS_RECO_FULL => $this->generateRisksPlan(),
                self::OPRISKS_RECO_FULL => $this->generateOperationalRisksPlan(),
                self::TABLE_RISK_OWNERS => $this->generateOwnersTable(),
            ]
        ]);

        return array_merge_recursive(
            $values,
            [
                'xml' => [
                    self::DISTRIB_EVAL_RISK => $this->generateWordXmlFromHtml(_WT($this->getRisksDistribution())),
                    self::DISTRIB_EVAL_OP_RISK => $this->generateWordXmlFromHtml(
                        _WT($this->getRisksDistribution(false))
                    ),
                    self::CURRENT_RISK_MAP => $this->generateCurrentRiskMap(),
                    self::TARGET_RISK_MAP => $this->generateCurrentRiskMap('targeted'),
                    self::TABLE_ASSET_CONTEXT => $this->generateAssetContextTable(),
                    self::RISKS_KIND_OF_TREATMENT => $this->generateRisksByKindOfMeasure(),
                    self::TABLE_AUDIT_INSTANCES => $this->generateTableAudit(),
                    self::TABLE_AUDIT_RISKS_OP => $this->generateTableAuditOp(),
                ],
            ]
        );
    }

    /**
     * Build values for Step 4 deliverable (Implementation plan)
     * @return array The key-value array
     */
    private function buildImplementationPlanValues()
    {
        return [
            'table' => [
                self::TABLE_IMPLEMENTATION_PLAN => $this->generateTableImplementationPlan(),
                self::TABLE_IMPLEMENTATION_HISTORY => $this->generateTableImplementationHistory(),
            ],
        ];
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
                self::TABLE_STATEMENT_OF_APPLICABILITY_SCALE => $this->generateTableStatementOfApplicabilityScale(
                    $soaScaleComments
                ),
                self::TABLE_STATEMENT_OF_APPLICABILITY => $this->generateTableStatementOfApplicability(
                    $referentialUuid
                ),
            ],
        ];
        if ($risksByControl) {
            $values['xml'][self::TABLE_RISKS_BY_CONTROL] = $this->generateTableRisksByControl($referentialUuid);
        } else {
            $values['txt'][self::TABLE_RISKS_BY_CONTROL] = null;
        }

        return $values;
    }

    /**
     * Build values for Step 5 deliverable (Record of Processing Activities)
     * @return array The key-value array
     */
    private function buildRecordOfProcessingActivitiesValues($record)
    {
        return [
            'xml' => [
                self::TABLE_RECORD_INFORMATION => $this->generateTableRecordGDPR($record),
                self::TABLE_RECORD_ACTORS => $this->generateTableRecordActors($record),
                self::TABLE_RECORD_PERSONAL_DATA => $this->generateTableRecordPersonalData($record),
                self::TABLE_RECORD_RECIPIENTS => $this->generateTableRecordRecipients($record),
                self::TABLE_RECORD_INTERNATIONAL_TRANSFERS => $this->generateTableRecordInternationalTransfers(
                    $record
                ),
                self::TABLE_RECORD_PROCESSORS => $this->generateTableRecordProcessors($record),
            ],
        ];
    }

    /**
     * Build values for Step 5 deliverable (All Records of Processing Activities)
     * @return array The key-value array
     */
    private function buildAllRecordsValues()
    {
        $values['xml'][self::TABLE_ALL_RECORDS] = $this->generateTableAllRecordsGDPR();

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
        $levelColumnWidth = 1.50;
        $consequencesColumnWidth = 6.00;
        $cidColumnWidth = (18 - $levelColumnWidth - $consequencesColumnWidth) / 3;

        $table->addRow(400, $this->tblHeader);
        $table->addCell(PhpWord\Shared\Converter::cmToTwip($levelColumnWidth), $this->grayCell)
            ->addText($this->anrTranslate('Level'), $this->boldFont, $this->centerParagraph);
        $table->addCell(PhpWord\Shared\Converter::cmToTwip($cidColumnWidth), $this->grayCell)
            ->addText($this->anrTranslate('Confidentiality'), $this->boldFont, $this->centerParagraph);
        $table->addCell(PhpWord\Shared\Converter::cmToTwip($cidColumnWidth), $this->grayCell)
            ->addText($this->anrTranslate('Integrity'), $this->boldFont, $this->centerParagraph);
        $table->addCell(PhpWord\Shared\Converter::cmToTwip($cidColumnWidth), $this->grayCell)
            ->addText($this->anrTranslate('Availability'), $this->boldFont, $this->centerParagraph);
        $table->addCell(PhpWord\Shared\Converter::cmToTwip($consequencesColumnWidth), $this->grayCell)
            ->addText($this->anrTranslate('Consequences'), $this->boldFont, $this->centerParagraph);

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
            $cidComments = [
                ScaleImpactTypeSuperClass::SCALE_TYPE_C => '',
                ScaleImpactTypeSuperClass::SCALE_TYPE_I => '',
                ScaleImpactTypeSuperClass::SCALE_TYPE_D => '',
            ];
            $consequenceLines = [];

            foreach ($scaleImpactTypesPerType as $type => $scaleImpactType) {
                $commentText = '';
                foreach ($scaleImpactType->getScaleComments() as $scaleComment) {
                    if ($scaleComment->getScaleIndex() === $scaleIndex) {
                        $commentText = $scaleComment->getComment($this->currentLangAnrIndex);
                        break;
                    }
                }

                if (in_array($type, ScaleImpactTypeSuperClass::getScaleImpactTypesCid(), true)) {
                    $cidComments[$type] = $commentText;
                    continue;
                }

                $consequenceLines[] = $this->anrTranslate($scaleImpactType->getLabel($this->currentLangAnrIndex))
                    . ': '
                    . $commentText;
            }

            $table->addRow(400);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip($levelColumnWidth), $this->vAlignCenterCell)
                ->addText((string)$scaleIndex, $this->normalFont, $this->centerParagraph);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip($cidColumnWidth), $this->vAlignCenterCell)
                ->addText(_WT($cidComments[ScaleImpactTypeSuperClass::SCALE_TYPE_C]), $this->normalFont, $this->leftParagraph);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip($cidColumnWidth), $this->vAlignCenterCell)
                ->addText(_WT($cidComments[ScaleImpactTypeSuperClass::SCALE_TYPE_I]), $this->normalFont, $this->leftParagraph);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip($cidColumnWidth), $this->vAlignCenterCell)
                ->addText(_WT($cidComments[ScaleImpactTypeSuperClass::SCALE_TYPE_D]), $this->normalFont, $this->leftParagraph);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip($consequencesColumnWidth), $this->vAlignCenterCell)
                ->addText(_WT(implode("\n", $consequenceLines)), $this->normalFont, $this->leftParagraph);
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
                if (!in_array($prod, $header, true)) {
                    $header[] = $prod;
                }
            }
        }
        asort($header);

        $impactColumnWidth = 1.20;
        $rowLabelWidth = 1.20;
        $cellWidth = max(0.65, (18 - $impactColumnWidth - $rowLabelWidth) / max(count($header), 1));
        $matrixFont = count($header) > 12 ? ['bold' => true, 'size' => 8] : $this->boldFont;
        $table->addRow();
        $table->addCell(
            PhpWord\Shared\Converter::cmToTwip($impactColumnWidth + $rowLabelWidth),
            $this->setColSpanCell(2)
        );
        $table->addCell(
            PhpWord\Shared\Converter::cmToTwip($cellWidth * count($header)),
            $this->setColSpanCell(count($header))
        )
            ->addText($this->anrTranslate('TxV'), $this->boldFont, $this->centerParagraph);
        $table->addRow();
        $table->addCell(PhpWord\Shared\Converter::cmToTwip($impactColumnWidth), $this->rotate90TextCell)
            ->addText($this->anrTranslate('Impact'), $this->boldFont, $this->centerParagraph);
        $table->addCell(PhpWord\Shared\Converter::cmToTwip($rowLabelWidth), $this->whiteBigBorderTable);
        foreach ($header as $MxV) {
            $table->addCell(PhpWord\Shared\Converter::cmToTwip($cellWidth), $this->whiteBigBorderTable)
                ->addText($MxV, $matrixFont, $this->centerParagraph);
        }

        for ($row = $impactsScale->getMin(); $row <= $impactsScale->getMax(); ++$row) {
            $table->addRow();
            $table->addCell(PhpWord\Shared\Converter::cmToTwip($impactColumnWidth), $this->continueCell);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip($rowLabelWidth), $this->whiteBigBorderTable)
                ->addText((string)$row, $matrixFont, $this->centerParagraph);

            foreach ($header as $MxV) {
                $value = $MxV * $row;

                $style = array_merge($this->whiteBigBorderTable, $this->setBgColorCell($value));
                $table->addCell(PhpWord\Shared\Converter::cmToTwip($cellWidth), $style)
                    ->addText((string)$value, $matrixFont, $this->centerParagraph);
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

        $levelColumnWidth = 1.50;
        $sizeColumn = (19 - $levelColumnWidth) / count($opRisksImpactsScales);

        $table->addRow(400, $this->tblHeader);
        $table->addCell(PhpWord\Shared\Converter::cmToTwip($levelColumnWidth), $this->grayCell)
            ->addText($this->anrTranslate('Level'), $this->boldFont, $this->centerParagraph);
        foreach ($opRisksImpactsScales as $opRiskImpactScale) {
            $table->addCell(PhpWord\Shared\Converter::cmToTwip($sizeColumn), $this->grayCell)
                ->addText(_WT($opRiskImpactScale['label']), $this->boldFont, $this->centerParagraph);
        }

        for ($row = $opRisksImpactsScaleMin; $row <= $opRisksImpactsScaleMax; ++$row) {
            $table->addRow(400);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip($levelColumnWidth), $this->restartAndTopCell)->addText(
                $opRisksImpactsScales[0]['comments'][$row]['scaleValue'] ?? 0,
                $this->normalFont,
                $this->centerParagraph
            );
            foreach ($opRisksImpactsScales as $opRiskImpactScale) {
                $table->addCell(PhpWord\Shared\Converter::cmToTwip($sizeColumn), $this->restartAndTopCell)->addText(
                    _WT($opRiskImpactScale['comments'][$row]['comment'] ?? ''),
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
                ->addText(_WT((string)$comment['comment']), $this->normalFont, $this->leftParagraph);
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

        $impactColumnWidth = 0.60;
        $labelColumnWidth = 0.85;
        $cellWidth = max(0.55, (18 - $impactColumnWidth - $labelColumnWidth) / max(count($header), 1));
        $rowHeight = 0.72;
        $matrixFont = count($header) > 7 ? ['bold' => true, 'size' => 8] : $this->boldFont;

        $table->addRow();
        $table->addCell(
            PhpWord\Shared\Converter::cmToTwip($impactColumnWidth + $labelColumnWidth),
            $this->setColSpanCell(2)
        );
        $table->addCell(null, $this->setColSpanCell(count($header)))
            ->addText($this->anrTranslate('Probability'), $this->boldFont, $this->centerParagraph);
        $table->addRow();
        $table->addCell(PhpWord\Shared\Converter::cmToTwip($impactColumnWidth), $this->rotate90TextCell)
            ->addText($this->anrTranslate('Impact'), $this->boldFont, $this->centerParagraph);
        $table->addCell(PhpWord\Shared\Converter::cmToTwip($labelColumnWidth), $this->whiteBigBorderTable);
        foreach ($header as $prob) {
            $table->addCell(PhpWord\Shared\Converter::cmToTwip($cellWidth), $this->whiteBigBorderTable)
                ->addText($prob, $matrixFont, $this->centerParagraph);
        }

        for ($row = $opRisksImpactsScaleMin; $row <= $opRisksImpactsScaleMax; ++$row) {
            $impactValue = $opRisksImpactsScales[0]['comments'][$row]['scaleValue'];
            $table->addRow(PhpWord\Shared\Converter::cmToTwip($rowHeight));
            $table->addCell(PhpWord\Shared\Converter::cmToTwip($impactColumnWidth), $this->continueCell);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip($labelColumnWidth), $this->whiteBigBorderTable)
                ->addText($impactValue, $matrixFont, $this->centerParagraph);
            foreach ($header as $prob) {
                $value = $prob * $impactValue;
                $style = array_merge($this->whiteBigBorderTable, $this->setBgColorCell($value, false));
                $table->addCell(PhpWord\Shared\Converter::cmToTwip($cellWidth), $style)
                    ->addText((string)$value, $matrixFont, $this->centerParagraph);
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
                ->addText(_WT((string)$interview['date']), $this->normalFont, $this->leftParagraph);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(4.00), $this->vAlignCenterCell)
                ->addText(_WT((string)$interview['service']), $this->normalFont, $this->leftParagraph);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(9.00), $this->vAlignCenterCell)
                ->addText(_WT((string)$interview['content']), $this->normalFont, $this->leftParagraph);
        }

        return $table;
    }

    private function generateReassessmentTriggersTable(): PhpWord\Element\Table
    {
        $tableWord = new PhpWord\PhpWord();
        $section = $tableWord->addSection();
        $table = $section->addTable($this->borderTable);
        $reassessmentTriggers = $this->reassessmentTriggerTable->findByAnrOrderedByPosition($this->anr);

        $table->addRow(400, $this->tblHeader);
        $table->addCell(PhpWord\Shared\Converter::cmToTwip(3.50), $this->grayCell)
            ->addText($this->anrTranslate('Trigger type'), $this->boldFont, $this->centerParagraph);
        $table->addCell(PhpWord\Shared\Converter::cmToTwip(5.50), $this->grayCell)
            ->addText($this->anrTranslate('Description'), $this->boldFont, $this->centerParagraph);
        $table->addCell(PhpWord\Shared\Converter::cmToTwip(6.00), $this->grayCell)
            ->addText($this->anrTranslate('Monitoring approach'), $this->boldFont, $this->centerParagraph);
        $table->addCell(PhpWord\Shared\Converter::cmToTwip(3.00), $this->grayCell)
            ->addText($this->anrTranslate('Status'), $this->boldFont, $this->centerParagraph);

        if (!count($reassessmentTriggers)) {
            $table->addRow(400);
            $table->addCell(
                PhpWord\Shared\Converter::cmToTwip(18.00),
                $this->setColSpanCell(4)
            )->addText(
                $this->anrTranslate('No reassessment trigger criteria have been defined for this analysis.'),
                $this->normalFont,
                $this->leftParagraph
            );

            return $table;
        }

        foreach ($reassessmentTriggers as $reassessmentTrigger) {
            $table->addRow(400);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(3.50), $this->vAlignCenterCell)
                ->addText(
                    _WT((string)$reassessmentTrigger->getTriggerType()),
                    $this->normalFont,
                    $this->leftParagraph
                );
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(5.50), $this->vAlignCenterCell)
                ->addText(_WT($reassessmentTrigger->getDescription()), $this->normalFont, $this->leftParagraph);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(6.00), $this->vAlignCenterCell)
                ->addText(
                    _WT((string)$reassessmentTrigger->getMonitoringApproach()),
                    $this->normalFont,
                    $this->leftParagraph
                );
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(3.00), $this->vAlignCenterCell)
                ->addText(
                    $reassessmentTrigger->isActive()
                        ? $this->anrTranslate('Active')
                        : $this->anrTranslate('Inactive'),
                    $this->normalFont,
                    $this->centerParagraph
                );
        }

        return $table;
    }

    private function generateInterestedPartiesTable(): PhpWord\Element\Table
    {
        $tableWord = new PhpWord\PhpWord();
        $section = $tableWord->addSection();
        $table = $section->addTable($this->borderTable);
        $interestedParties = $this->interestedPartyTable->findByAnrOrderedByPosition($this->anr);

        $table->addRow(400, $this->tblHeader);
        $table->addCell(PhpWord\Shared\Converter::cmToTwip(5.50), $this->grayCell)
            ->addText($this->anrTranslate('Stakeholder'), $this->boldFont, $this->centerParagraph);
        $table->addCell(PhpWord\Shared\Converter::cmToTwip(12.50), $this->grayCell)
            ->addText($this->anrTranslate('Requirement'), $this->boldFont, $this->centerParagraph);

        if (!count($interestedParties)) {
            $table->addRow(400);
            $table->addCell(
                PhpWord\Shared\Converter::cmToTwip(18.00),
                $this->setColSpanCell(2)
            )->addText(
                $this->anrTranslate(
                    'No interested parties and requirements have been defined for this analysis.'
                ),
                $this->normalFont,
                $this->leftParagraph
            );

            return $table;
        }

        foreach ($interestedParties as $interestedParty) {
            $table->addRow(400);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(5.50), $this->vAlignCenterCell)
                ->addText(_WT($interestedParty->getStakeholder()), $this->normalFont, $this->leftParagraph);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(12.50), $this->vAlignCenterCell)
                ->addText(_WT($interestedParty->getRequirement()), $this->normalFont, $this->leftParagraph);
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
        $instanceRisks = $this->instanceRiskTable->findByAnrAndOrderByParams(
            $this->anr,
            ['ir.cacheMaxRisk' => 'DESC']
        );

        $globalObject = [];
        $mem_risks = [];
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
                    'riskSource' => $instanceRisk->getRiskSource()?->getLabel() ?? '-',
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
                    'lastReviewDate' => $instanceRisk->getLastReviewDate()?->format('Y-m-d') ?? '-',
                    'residualRiskAcceptance' => $this->formatResidualRiskAcceptance($instanceRisk),
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
            $isPdfOutput = $this->currentOutputFormat === self::OUTPUT_FORMAT_PDF;
            $auditInfoTableStyle = $isPdfOutput
                ? array_merge($this->borderTable, [
                    'layout' => PhpWord\Style\Table::LAYOUT_FIXED,
                    'align' => 'center',
                    'cellMarginLeft' => 55,
                    'cellMarginRight' => 55,
                ])
                : $this->borderTable;
            $auditInfoHeaderFont = $isPdfOutput ? array_merge($this->boldFont, ['size' => 9]) : $this->whiteFont;
            $auditInfoNormalFont = $isPdfOutput ? array_merge($this->normalFont, ['size' => 9]) : $this->normalFont;
            $auditInfoBoldFont = $isPdfOutput ? array_merge($this->boldFont, ['size' => 9]) : $this->boldFont;
            $auditInfoCenterParagraph = ['alignment' => 'center', 'spaceAfter' => '0', 'indent' => 0];
            $auditInfoLeftParagraph = ['alignment' => 'left', 'spaceAfter' => '0', 'indent' => 0];
            $auditInfoRestartHeaderCell = $isPdfOutput ? $this->restartAndGrayCell : $this->restartAndBlackCell;
            $auditInfoContinueHeaderCell = $isPdfOutput ? $this->continueAndGrayCell : $this->continueAndBlackCell;
            $auditInfoHeaderCell = $isPdfOutput ? $this->grayCell : $this->blackCell;
            $auditInfoHeaderBackground = $isPdfOutput ? 'DFDFDF' : '444444';

            // Keep the Appendix F informational-risk table inside portrait PDF pages.
            // The PDF version is intentionally a bit narrower than the DOCX layout, but not cramped.
            $riskSourceWidth = $isPdfOutput ? 1.80 : 2.90;
            $impactWidth = $isPdfOutput ? 0.68 : 1.15;
            $threatLabelWidth = $isPdfOutput ? 2.30 : 5.00;
            $threatRateWidth = $isPdfOutput ? 1.45 : 2.00;
            $vulnerabilityLabelWidth = $isPdfOutput ? 3.35 : 5.00;
            $descriptionWidth = $isPdfOutput ? 2.65 : 5.00;
            $vulnerabilityRateWidth = $isPdfOutput ? 1.40 : 2.00;
            $currentRiskWidth = $isPdfOutput ? 1.00 : 1.70;
            $treatmentWidth = $isPdfOutput ? 2.00 : 2.50;
            $targetRiskWidth = $isPdfOutput ? 1.70 : 2.40;
            $lastReviewDateWidth = $isPdfOutput ? 1.55 : 2.20;
            $residualRiskAcceptanceWidth = $isPdfOutput ? 5.30 : 6.00;
            $impactGroupWidth = $impactWidth * 3;
            $threatGroupWidth = $threatLabelWidth + $threatRateWidth;
            $vulnerabilityGroupWidth = $vulnerabilityLabelWidth + $descriptionWidth + $vulnerabilityRateWidth;
            $currentRiskGroupWidth = $currentRiskWidth * 3;
            $auditInfoColumnCount = 9;
            $contextRowWidth = $riskSourceWidth
                + $impactGroupWidth
                + $threatGroupWidth
                + $vulnerabilityGroupWidth
                + $currentRiskGroupWidth
                + $treatmentWidth
                + $targetRiskWidth
                + $lastReviewDateWidth
                + $residualRiskAcceptanceWidth;

            $tableWord = new PhpWord\PhpWord();
            $section = $tableWord->addSection();
            $createAuditInfoTable = function () use (
                $section,
                $auditInfoTableStyle,
                $auditInfoHeaderFont,
                $auditInfoCenterParagraph,
                $auditInfoRestartHeaderCell,
                $auditInfoContinueHeaderCell,
                $auditInfoHeaderCell,
                $auditInfoHeaderBackground,
                $riskSourceWidth,
                $impactWidth,
                $threatLabelWidth,
                $threatRateWidth,
                $vulnerabilityLabelWidth,
                $descriptionWidth,
                $vulnerabilityRateWidth,
                $currentRiskWidth,
                $treatmentWidth,
                $targetRiskWidth,
                $lastReviewDateWidth,
                $residualRiskAcceptanceWidth
            ) {
                $table = $section->addTable($auditInfoTableStyle);
                $table->addRow(400, $this->tblHeader);
                $table->addCell(
                    PhpWord\Shared\Converter::cmToTwip($riskSourceWidth),
                    $auditInfoRestartHeaderCell
                )->addText($this->anrTranslate('Risk source'), $auditInfoHeaderFont, $auditInfoCenterParagraph);
                $table->addCell(
                    PhpWord\Shared\Converter::cmToTwip($impactWidth * 3),
                    $this->setColSpanCell(3, $auditInfoHeaderBackground)
                )->addText($this->anrTranslate('Impact'), $auditInfoHeaderFont, $auditInfoCenterParagraph);
                $table->addCell(
                    PhpWord\Shared\Converter::cmToTwip($threatLabelWidth + $threatRateWidth),
                    $this->setColSpanCell(2, $auditInfoHeaderBackground)
                )->addText($this->anrTranslate('Threat'), $auditInfoHeaderFont, $auditInfoCenterParagraph);
                $table->addCell(
                    PhpWord\Shared\Converter::cmToTwip(
                        $vulnerabilityLabelWidth + $descriptionWidth + $vulnerabilityRateWidth
                    ),
                    $this->setColSpanCell(3, $auditInfoHeaderBackground)
                )->addText($this->anrTranslate('Vulnerability'), $auditInfoHeaderFont, $auditInfoCenterParagraph);
                $table->addCell(
                    PhpWord\Shared\Converter::cmToTwip($currentRiskWidth * 3),
                    $this->setColSpanCell(3, $auditInfoHeaderBackground)
                )->addText($this->anrTranslate('Current risk'), $auditInfoHeaderFont, $auditInfoCenterParagraph);
                $table->addCell(
                    PhpWord\Shared\Converter::cmToTwip($treatmentWidth),
                    $auditInfoRestartHeaderCell
                )->addText($this->anrTranslate('Treatment'), $auditInfoHeaderFont, $auditInfoCenterParagraph);
                $table->addCell(
                    PhpWord\Shared\Converter::cmToTwip($targetRiskWidth),
                    $auditInfoRestartHeaderCell
                )->addText($this->anrTranslate('Residual risk'), $auditInfoHeaderFont, $auditInfoCenterParagraph);
                $table->addCell(
                    PhpWord\Shared\Converter::cmToTwip($lastReviewDateWidth),
                    $auditInfoRestartHeaderCell
                )->addText($this->anrTranslate('Last review date'), $auditInfoHeaderFont, $auditInfoCenterParagraph);
                $table->addCell(
                    PhpWord\Shared\Converter::cmToTwip($residualRiskAcceptanceWidth),
                    $auditInfoRestartHeaderCell
                )->addText(
                    $this->anrTranslate('Residual risk decision'),
                    $auditInfoHeaderFont,
                    $auditInfoCenterParagraph
                );

                $table->addRow(400, $this->tblHeader);
                $table->addCell(PhpWord\Shared\Converter::cmToTwip($riskSourceWidth), $auditInfoContinueHeaderCell);
                $table->addCell(PhpWord\Shared\Converter::cmToTwip($impactWidth), $auditInfoHeaderCell)
                    ->addText('C', $auditInfoHeaderFont, $auditInfoCenterParagraph);
                $table->addCell(PhpWord\Shared\Converter::cmToTwip($impactWidth), $auditInfoHeaderCell)
                    ->addText('I', $auditInfoHeaderFont, $auditInfoCenterParagraph);
                $table->addCell(PhpWord\Shared\Converter::cmToTwip($impactWidth), $auditInfoHeaderCell)
                    ->addText($this->anrTranslate('A'), $auditInfoHeaderFont, $auditInfoCenterParagraph);
                $table->addCell(PhpWord\Shared\Converter::cmToTwip($threatLabelWidth), $auditInfoHeaderCell)
                    ->addText($this->anrTranslate('Label'), $auditInfoHeaderFont, $auditInfoCenterParagraph);
                $table->addCell(PhpWord\Shared\Converter::cmToTwip($threatRateWidth), $auditInfoHeaderCell)
                    ->addText($this->anrTranslate('Prob.'), $auditInfoHeaderFont, $auditInfoCenterParagraph);
                $table->addCell(PhpWord\Shared\Converter::cmToTwip($vulnerabilityLabelWidth), $auditInfoHeaderCell)
                    ->addText($this->anrTranslate('Label'), $auditInfoHeaderFont, $auditInfoCenterParagraph);
                $table->addCell(PhpWord\Shared\Converter::cmToTwip($descriptionWidth), $auditInfoHeaderCell)
                    ->addText($this->anrTranslate('Existing controls'), $auditInfoHeaderFont, $auditInfoCenterParagraph);
                $table->addCell(PhpWord\Shared\Converter::cmToTwip($vulnerabilityRateWidth), $auditInfoHeaderCell)
                    ->addText($this->anrTranslate('Qualif.'), $auditInfoHeaderFont, $auditInfoCenterParagraph);
                $table->addCell(PhpWord\Shared\Converter::cmToTwip($currentRiskWidth), $auditInfoHeaderCell)
                    ->addText('C', $auditInfoHeaderFont, $auditInfoCenterParagraph);
                $table->addCell(PhpWord\Shared\Converter::cmToTwip($currentRiskWidth), $auditInfoHeaderCell)
                    ->addText('I', $auditInfoHeaderFont, $auditInfoCenterParagraph);
                $table->addCell(PhpWord\Shared\Converter::cmToTwip($currentRiskWidth), $auditInfoHeaderCell)
                    ->addText($this->anrTranslate('A'), $auditInfoHeaderFont, $auditInfoCenterParagraph);
                $table->addCell(PhpWord\Shared\Converter::cmToTwip($treatmentWidth), $auditInfoContinueHeaderCell);
                $table->addCell(PhpWord\Shared\Converter::cmToTwip($targetRiskWidth), $auditInfoContinueHeaderCell);
                $table->addCell(PhpWord\Shared\Converter::cmToTwip($lastReviewDateWidth), $auditInfoContinueHeaderCell);
                $table->addCell(
                    PhpWord\Shared\Converter::cmToTwip($residualRiskAcceptanceWidth),
                    $auditInfoContinueHeaderCell
                );

                return $table;
            };
            for ($i = 0; $i < $maxLevelDeep + 1; $i++) {
                $tableWord->addTitleStyle($i + 3, $this->titleFont);
            }

            if (in_array('true', $global, true)) {
                $section->addTitle($this->anrTranslate('Global assets'), 3);
            }

            foreach ($mem_risks as $data) {
                if (empty($data['tree'])) {
                    $section->addTitle(_WT($data['ctx']), 4);
                    $table = $createAuditInfoTable();
                } else {
                    $treeNum = count($data['tree']);
                    for ($i = 0; $i < $treeNum; $i++) {
                        if ($i <= $maxLevelTitle - 1 && $title[$i] !== $data['tree'][$i]['id']) {
                            $section->addTitle(_WT($data['tree'][$i]['name' . $this->currentLangAnrIndex]), $i + 3);
                            $title[$i] = $data['tree'][$i]['id'];
                            if ($maxLevelTitle === $treeNum && empty($data['risks'])) {
                                $data['risks'] = true;
                            }
                        }
                    }
                }

                if (!empty($data['risks']) && $data['risks'] !== true) {
                    if ($data['global'] === false) {
                        $section->addTextBreak();
                        $table = $createAuditInfoTable();
                        $table->addRow(400);
                        $table->addCell(
                            PhpWord\Shared\Converter::cmToTwip($contextRowWidth),
                            $this->setColSpanCell($auditInfoColumnCount, 'DFDFDF')
                        )->addText(_WT($data['ctx']), $auditInfoBoldFont, $this->leftParagraph);
                    }
                    foreach ($data['risks'] as $r) {
                        foreach ($r as $key => $value) {
                            if ($this->isUnavailableDisplayValue($value)) {
                                $r[$key] = '-';
                            }
                        }
                        $riskSourceParagraph = $r['riskSource'] === '-'
                            ? $auditInfoCenterParagraph
                            : $auditInfoLeftParagraph;
                        $threatParagraph = $r['threat'] === '-'
                            ? $auditInfoCenterParagraph
                            : $auditInfoLeftParagraph;
                        $vulnerabilityParagraph = $r['vulnerability'] === '-'
                            ? $auditInfoCenterParagraph
                            : $auditInfoLeftParagraph;
                        $descriptionParagraph = $r['comment'] === '-'
                            ? $auditInfoCenterParagraph
                            : $auditInfoLeftParagraph;
                        $residualRiskAcceptanceParagraph = $r['residualRiskAcceptance'] === '-'
                            ? $auditInfoCenterParagraph
                            : $auditInfoLeftParagraph;

                        $table->addRow(400);
                        $table->addCell(PhpWord\Shared\Converter::cmToTwip($riskSourceWidth), $this->vAlignCenterCell)
                            ->addText(_WT($r['riskSource']), $auditInfoNormalFont, $riskSourceParagraph);
                        $table->addCell(PhpWord\Shared\Converter::cmToTwip($impactWidth), $this->vAlignCenterCell)
                            ->addText($r['impactC'], $auditInfoNormalFont, $auditInfoCenterParagraph);
                        $table->addCell(PhpWord\Shared\Converter::cmToTwip($impactWidth), $this->vAlignCenterCell)
                            ->addText($r['impactI'], $auditInfoNormalFont, $auditInfoCenterParagraph);
                        $table->addCell(PhpWord\Shared\Converter::cmToTwip($impactWidth), $this->vAlignCenterCell)
                            ->addText($r['impactA'], $auditInfoNormalFont, $auditInfoCenterParagraph);
                        $table->addCell(PhpWord\Shared\Converter::cmToTwip($threatLabelWidth), $this->vAlignCenterCell)
                            ->addText(_WT($r['threat']), $auditInfoNormalFont, $threatParagraph);
                        $table->addCell(PhpWord\Shared\Converter::cmToTwip($threatRateWidth), $this->vAlignCenterCell)
                            ->addText($r['threatRate'], $auditInfoNormalFont, $auditInfoCenterParagraph);
                        $table->addCell(
                            PhpWord\Shared\Converter::cmToTwip($vulnerabilityLabelWidth),
                            $this->vAlignCenterCell
                        )->addText(_WT($r['vulnerability']), $auditInfoNormalFont, $vulnerabilityParagraph);
                        $table->addCell(PhpWord\Shared\Converter::cmToTwip($descriptionWidth), $this->vAlignCenterCell)
                            ->addText(_WT($r['comment']), $auditInfoNormalFont, $descriptionParagraph);
                        $table->addCell(
                            PhpWord\Shared\Converter::cmToTwip($vulnerabilityRateWidth),
                            $this->vAlignCenterCell
                        )->addText($r['vulRate'], $auditInfoNormalFont, $auditInfoCenterParagraph);
                        $table->addCell(
                            PhpWord\Shared\Converter::cmToTwip($currentRiskWidth),
                            $this->setBgColorCell($r['riskC'])
                        )->addText($r['riskC'], $auditInfoBoldFont, $auditInfoCenterParagraph);
                        $table->addCell(
                            PhpWord\Shared\Converter::cmToTwip($currentRiskWidth),
                            $this->setBgColorCell($r['riskI'])
                        )->addText($r['riskI'], $auditInfoBoldFont, $auditInfoCenterParagraph);
                        $table->addCell(
                            PhpWord\Shared\Converter::cmToTwip($currentRiskWidth),
                            $this->setBgColorCell($r['riskA'])
                        )->addText($r['riskA'], $auditInfoBoldFont, $auditInfoCenterParagraph);
                        $table->addCell(PhpWord\Shared\Converter::cmToTwip($treatmentWidth), $this->vAlignCenterCell)
                            ->addText($this->anrTranslate(
                                $r['treatmentName']
                            ), $auditInfoNormalFont, $auditInfoCenterParagraph);
                        $table->addCell(
                            PhpWord\Shared\Converter::cmToTwip($targetRiskWidth),
                            $this->setBgColorCell($r['targetRisk'])
                        )->addText($r['targetRisk'], $auditInfoBoldFont, $auditInfoCenterParagraph);
                        $table->addCell(
                            PhpWord\Shared\Converter::cmToTwip($lastReviewDateWidth),
                            $this->vAlignCenterCell
                        )->addText(_WT($r['lastReviewDate']), $auditInfoNormalFont, $auditInfoCenterParagraph);
                        $table->addCell(
                            PhpWord\Shared\Converter::cmToTwip($residualRiskAcceptanceWidth),
                            $this->vAlignCenterCell
                        )->addText(
                            _WT($r['residualRiskAcceptance']),
                            $auditInfoNormalFont,
                            $residualRiskAcceptanceParagraph
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
                $levelTree = count($ascendants);
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
                'riskSource' => $operationalInstanceRisk->getRiskSource()?->getLabel() ?? '-',
                'label' => $operationalInstanceRisk->getRiskCacheLabel($this->currentLangAnrIndex),
                'brutProb' => $operationalInstanceRisk->getBrutProb(),
                'brutRisk' => $operationalInstanceRisk->getCacheBrutRisk(),
                'netProb' => $operationalInstanceRisk->getNetProb(),
                'netRisk' => $operationalInstanceRisk->getCacheNetRisk(),
                'scales' => $scalesData,
                'comment' => $operationalInstanceRisk->getComment(),
                'targetedRisk' => $operationalInstanceRisk->getCacheTargetedRisk(),
                'treatmentName' => $operationalInstanceRisk->getTreatmentName(),
                'lastReviewDate' => $operationalInstanceRisk->getLastReviewDate()?->format('Y-m-d') ?? '-',
                'residualRiskAcceptance' => $this->formatResidualRiskAcceptanceValues(
                    $operationalInstanceRisk->getResidualRiskDecision(),
                    $operationalInstanceRisk->getResidualAcceptanceApproverSupervisor()?->getName(),
                    $operationalInstanceRisk->getResidualRiskDecidedAt()?->format('Y-m-d'),
                    $operationalInstanceRisk->getResidualAcceptancePerformedByName(),
                    $operationalInstanceRisk->isResidualAcceptancePerformedOnBehalf(),
                    $operationalInstanceRisk->getResidualRiskJustification()
                ),
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
            $isPdfOutput = $this->currentOutputFormat === self::OUTPUT_FORMAT_PDF;
            $opRiskScaleCellWidth = $isPdfOutput ? 0.82 : 0.70;
            $sizeCellImpact = count($opRisksImpactsScales) * $opRiskScaleCellWidth;
            $opRiskSourceWidth = $isPdfOutput ? 1.80 : 2.50;
            $opRiskDescriptionWidth = $isPdfOutput ? 5.20 : 7.50;
            $opRiskProbabilityWidth = $isPdfOutput ? 0.90 : 1.00;
            $opRiskCurrentWidth = $isPdfOutput ? 1.50 : 1.70;
            $opExistingControlsWidth = $isPdfOutput ? 4.85 : 8.00;
            $opTreatmentWidth = $isPdfOutput ? 1.90 : 2.00;
            $opResidualRiskWidth = $isPdfOutput ? 1.45 : 2.00;
            $opLastReviewDateWidth = $isPdfOutput ? 1.70 : 2.00;
            $opResidualDecisionWidth = $isPdfOutput ? 5.00 : 6.80;
            $opHeaderFont = $isPdfOutput ? $this->boldFont : $this->whiteFont;
            $opHeaderCell = $isPdfOutput ? $this->grayCell : $this->blackCell;
            $opRestartHeaderCell = $isPdfOutput ? $this->restartAndGrayCell : $this->restartAndBlackCell;
            $opContinueHeaderCell = $isPdfOutput ? $this->continueAndGrayCell : $this->continueAndBlackCell;
            $opHeaderBackground = $isPdfOutput ? 'DFDFDF' : '444444';
            $auditOpTableStyle = $isPdfOutput
                ? array_merge($this->borderTable, [
                    'layout' => PhpWord\Style\Table::LAYOUT_FIXED,
                    'align' => 'center',
                    'cellMarginLeft' => 55,
                    'cellMarginRight' => 55,
                ])
                : $this->borderTable;
            $opAuditColumnCount = $this->anr->showRolfBrut()
                ? 11 + count($opRisksImpactsScales) * 2
                : 9 + count($opRisksImpactsScales);
            $opContextRowWidth = $opRiskSourceWidth
                + $opRiskDescriptionWidth
                + $opTreatmentWidth
                + $opResidualRiskWidth
                + $opLastReviewDateWidth
                + $opResidualDecisionWidth
                + $opRiskProbabilityWidth
                + $sizeCellImpact
                + $opRiskCurrentWidth
                + $opExistingControlsWidth;
            if ($this->anr->showRolfBrut()) {
                $opContextRowWidth += $opRiskProbabilityWidth + $sizeCellImpact + $opRiskCurrentWidth;
            }

            $tableWord = new PhpWord\PhpWord();
            $section = $tableWord->addSection();
            $maxLevelDeep = ($maxLevelDeep <= 4 ? $maxLevelDeep : 4);
            for ($i = 0; $i < $maxLevelDeep; $i++) {
                $tableWord->addTitleStyle($i + 3, $this->titleFont);
            }

            $maxLevelTitle = ($maxLevelDeep === 1 ? $maxLevelDeep : $maxLevelDeep - 1);

            $title = array_fill(0, $maxLevelDeep, null);
            $createAuditOpTable = function () use (
                $section,
                $auditOpTableStyle,
                $opHeaderFont,
                $opHeaderCell,
                $opRestartHeaderCell,
                $opContinueHeaderCell,
                $opHeaderBackground,
                $opRisksImpactsScales,
                $sizeCellImpact,
                $opRiskScaleCellWidth,
                $opRiskSourceWidth,
                $opRiskDescriptionWidth,
                $opRiskProbabilityWidth,
                $opRiskCurrentWidth,
                $opExistingControlsWidth,
                $opTreatmentWidth,
                $opResidualRiskWidth,
                $opLastReviewDateWidth,
                $opResidualDecisionWidth
            ) {
                $table = $section->addTable($auditOpTableStyle);
                $table->addRow(400, $this->tblHeader);
                $table->addCell(PhpWord\Shared\Converter::cmToTwip($opRiskSourceWidth), $opRestartHeaderCell)
                    ->addText(
                        $this->anrTranslate('Risk source'),
                        $opHeaderFont,
                        $this->centerParagraph
                    );
                $table->addCell(PhpWord\Shared\Converter::cmToTwip($opRiskDescriptionWidth), $opRestartHeaderCell)
                    ->addText(
                        $this->anrTranslate('Risk description'),
                        $opHeaderFont,
                        $this->centerParagraph
                    );
                if ($this->anr->showRolfBrut()) {
                    $table->addCell(
                        PhpWord\Shared\Converter::cmToTwip($opRiskProbabilityWidth + $sizeCellImpact + $opRiskCurrentWidth),
                        $this->setColSpanCell(2 + count($opRisksImpactsScales), $opHeaderBackground)
                    )->addText(
                        $this->anrTranslate('Inherent risk'),
                        $opHeaderFont,
                        $this->centerParagraph
                    );
                }
                $table->addCell(
                    PhpWord\Shared\Converter::cmToTwip(
                        $opRiskProbabilityWidth + $sizeCellImpact + $opRiskCurrentWidth + $opExistingControlsWidth
                    ),
                    $this->setColSpanCell(3 + count($opRisksImpactsScales), $opHeaderBackground)
                )
                    ->addText($this->anrTranslate('Net risk'), $opHeaderFont, $this->centerParagraph);
                $table->addCell(PhpWord\Shared\Converter::cmToTwip($opTreatmentWidth), $opRestartHeaderCell)
                    ->addText($this->anrTranslate('Treatment'), $opHeaderFont, $this->centerParagraph);
                $table->addCell(PhpWord\Shared\Converter::cmToTwip($opResidualRiskWidth), $opRestartHeaderCell)
                    ->addText(
                        $this->anrTranslate('Residual risk'),
                        $opHeaderFont,
                        $this->centerParagraph
                    );
                $table->addCell(PhpWord\Shared\Converter::cmToTwip($opLastReviewDateWidth), $opRestartHeaderCell)
                    ->addText(
                        $this->anrTranslate('Last review date'),
                        $opHeaderFont,
                        $this->centerParagraph
                    );
                $table->addCell(PhpWord\Shared\Converter::cmToTwip($opResidualDecisionWidth), $opRestartHeaderCell)
                    ->addText(
                        $this->anrTranslate('Residual risk decision'),
                        $opHeaderFont,
                        $this->centerParagraph
                    );

                $table->addRow(400, $this->tblHeader);
                $table->addCell(PhpWord\Shared\Converter::cmToTwip($opRiskSourceWidth), $opContinueHeaderCell);
                $table->addCell(PhpWord\Shared\Converter::cmToTwip($opRiskDescriptionWidth), $opContinueHeaderCell);
                if ($this->anr->showRolfBrut()) {
                    $table->addCell(PhpWord\Shared\Converter::cmToTwip($opRiskProbabilityWidth), $opRestartHeaderCell)
                        ->addText($this->anrTranslate('Prob.'), $opHeaderFont, $this->centerParagraph);
                    $table->addCell(
                        PhpWord\Shared\Converter::cmToTwip($sizeCellImpact),
                        $this->setColSpanCell(count($opRisksImpactsScales), $opHeaderBackground)
                    )->addText($this->anrTranslate('Impact'), $opHeaderFont, $this->centerParagraph);
                    $table->addCell(PhpWord\Shared\Converter::cmToTwip($opRiskCurrentWidth), $opRestartHeaderCell)
                        ->addText(
                            $this->anrTranslate('Current risk'),
                            $opHeaderFont,
                            $this->centerParagraph
                        );
                }
                $table->addCell(PhpWord\Shared\Converter::cmToTwip($opRiskProbabilityWidth), $opRestartHeaderCell)
                    ->addText($this->anrTranslate('Prob.'), $opHeaderFont, $this->centerParagraph);
                $table->addCell(
                    PhpWord\Shared\Converter::cmToTwip($sizeCellImpact),
                    $this->setColSpanCell(count($opRisksImpactsScales), $opHeaderBackground)
                )->addText($this->anrTranslate('Impact'), $opHeaderFont, $this->centerParagraph);
                $table->addCell(PhpWord\Shared\Converter::cmToTwip($opRiskCurrentWidth), $opRestartHeaderCell)
                    ->addText(
                        $this->anrTranslate('Current risk'),
                        $opHeaderFont,
                        $this->centerParagraph
                    );
                $table->addCell(PhpWord\Shared\Converter::cmToTwip($opExistingControlsWidth), $opRestartHeaderCell)
                    ->addText(
                        $this->anrTranslate('Existing controls'),
                        $opHeaderFont,
                        $this->centerParagraph
                    );
                $table->addCell(PhpWord\Shared\Converter::cmToTwip($opTreatmentWidth), $opContinueHeaderCell);
                $table->addCell(PhpWord\Shared\Converter::cmToTwip($opResidualRiskWidth), $opContinueHeaderCell);
                $table->addCell(PhpWord\Shared\Converter::cmToTwip($opLastReviewDateWidth), $opContinueHeaderCell);
                $table->addCell(PhpWord\Shared\Converter::cmToTwip($opResidualDecisionWidth), $opContinueHeaderCell);

                $table->addRow(PhpWord\Shared\Converter::cmToTwip(1.00), $this->tblHeader);
                $table->addCell(PhpWord\Shared\Converter::cmToTwip($opRiskSourceWidth), $opContinueHeaderCell);
                $table->addCell(PhpWord\Shared\Converter::cmToTwip($opRiskDescriptionWidth), $opContinueHeaderCell);

                if ($this->anr->showRolfBrut()) {
                    $table->addCell(PhpWord\Shared\Converter::cmToTwip($opRiskProbabilityWidth), $opContinueHeaderCell);
                    foreach ($opRisksImpactsScales as $opRiskImpactScale) {
                        $label = mb_substr(_WT($opRiskImpactScale['label']), 0, 3) . '.';
                        $table->addCell(
                            PhpWord\Shared\Converter::cmToTwip($opRiskScaleCellWidth),
                            array_merge($this->rotate90TextCell, ['bgcolor' => $opHeaderBackground])
                        )->addText($label, $opHeaderFont, $this->verticalCenterParagraph);
                    }
                    $table->addCell(PhpWord\Shared\Converter::cmToTwip($opRiskCurrentWidth), $opContinueHeaderCell);
                }
                $table->addCell(PhpWord\Shared\Converter::cmToTwip($opRiskProbabilityWidth), $opContinueHeaderCell);
                foreach ($opRisksImpactsScales as $opRiskImpactScale) {
                    $label = mb_substr(_WT($opRiskImpactScale['label']), 0, 3) . '.';
                    $table->addCell(
                        PhpWord\Shared\Converter::cmToTwip($opRiskScaleCellWidth),
                        array_merge($this->rotate90TextCell, ['bgcolor' => $opHeaderBackground])
                    )->addText($label, $opHeaderFont, $this->verticalCenterParagraph);
                }
                $table->addCell(PhpWord\Shared\Converter::cmToTwip($opRiskCurrentWidth), $opContinueHeaderCell);
                $table->addCell(PhpWord\Shared\Converter::cmToTwip($opExistingControlsWidth), $opContinueHeaderCell);
                $table->addCell(PhpWord\Shared\Converter::cmToTwip($opTreatmentWidth), $opContinueHeaderCell);
                $table->addCell(PhpWord\Shared\Converter::cmToTwip($opResidualRiskWidth), $opContinueHeaderCell);
                $table->addCell(PhpWord\Shared\Converter::cmToTwip($opLastReviewDateWidth), $opContinueHeaderCell);
                $table->addCell(PhpWord\Shared\Converter::cmToTwip($opResidualDecisionWidth), $opContinueHeaderCell);

                return $table;
            };

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
                    }
                }

                if (!empty($data['risks']) && $data['risks'] !== true) {
                    $styleCell = $this->setColSpanCell($opAuditColumnCount, 'DFDFDF');
                    if ($this->anr->showRolfBrut()) {
                        $styleCell = $this->setColSpanCell($opAuditColumnCount, 'DFDFDF');
                    }
                    $section->addTextBreak();
                    $table = $createAuditOpTable();
                    $table->addRow(400);
                    $table->addCell(PhpWord\Shared\Converter::cmToTwip($opContextRowWidth), $styleCell)
                        ->addText(_WT($data['path']), $this->boldFont, $this->leftParagraph);
                    foreach ($data['risks'] as $r) {
                        foreach ($r as $key => $value) {
                            if ($this->isUnavailableDisplayValue($value)) {
                                $r[$key] = '-';
                            }
                        }
                        $opRiskSourceParagraph = $r['riskSource'] === '-'
                            ? $this->centerParagraph
                            : $this->leftParagraph;
                        $opResidualDecisionParagraph = $r['residualRiskAcceptance'] === '-'
                            ? $this->centerParagraph
                            : $this->leftParagraph;
                        $table->addRow(400);
                        $table->addCell(PhpWord\Shared\Converter::cmToTwip($opRiskSourceWidth), $this->vAlignCenterCell)
                            ->addText(_WT($r['riskSource']), $this->normalFont, $opRiskSourceParagraph);
                        $table->addCell(PhpWord\Shared\Converter::cmToTwip($opRiskDescriptionWidth), $this->vAlignCenterCell)
                            ->addText(_WT($r['label']), $this->normalFont, $this->leftParagraph);
                        if ($this->anr->showRolfBrut()) {
                            $table->addCell(PhpWord\Shared\Converter::cmToTwip($opRiskProbabilityWidth), $this->vAlignCenterCell)
                                ->addText($r['brutProb'], $this->normalFont, $this->centerParagraph);
                            foreach ($opRisksImpactsScales as $opRiskImpactScale) {
                                $table->addCell(
                                    PhpWord\Shared\Converter::cmToTwip($opRiskScaleCellWidth),
                                    $this->vAlignCenterCell
                                )
                                    ->addText(
                                        $r['scales'][$opRiskImpactScale['id']]['brutValue'],
                                        $this->normalFont,
                                        $this->centerParagraph
                                    );
                            }
                            $table->addCell(
                                PhpWord\Shared\Converter::cmToTwip($opRiskCurrentWidth),
                                $this->setBgColorCell($r['brutRisk'], false)
                            )->addText($r['brutRisk'], $this->boldFont, $this->centerParagraph);
                        }
                        $table->addCell(PhpWord\Shared\Converter::cmToTwip($opRiskProbabilityWidth), $this->vAlignCenterCell)
                            ->addText($r['netProb'], $this->normalFont, $this->centerParagraph);
                        foreach ($opRisksImpactsScales as $opRiskImpactScale) {
                            $table->addCell(
                                PhpWord\Shared\Converter::cmToTwip($opRiskScaleCellWidth),
                                $this->vAlignCenterCell
                            )
                                ->addText(
                                    $r['scales'][$opRiskImpactScale['id']]['netValue'],
                                    $this->normalFont,
                                    $this->centerParagraph
                                );
                        }
                        $table->addCell(
                            PhpWord\Shared\Converter::cmToTwip($opRiskCurrentWidth),
                            $this->setBgColorCell($r['netRisk'], false)
                        )->addText($r['netRisk'], $this->boldFont, $this->centerParagraph);
                        $table->addCell(PhpWord\Shared\Converter::cmToTwip($opExistingControlsWidth), $this->vAlignCenterCell)
                            ->addText(_WT($r['comment']), $this->normalFont, $this->leftParagraph);
                        $table->addCell(PhpWord\Shared\Converter::cmToTwip($opTreatmentWidth), $this->vAlignCenterCell)
                            ->addText($this->anrTranslate(
                                $r['treatmentName']
                            ), $this->normalFont, $this->centerParagraph);
                        $targetedRisk = $r['targetedRisk'] === '-' ? $r['netRisk'] : $r['targetedRisk'];
                        $table->addCell(
                            PhpWord\Shared\Converter::cmToTwip($opResidualRiskWidth),
                            $this->setBgColorCell($targetedRisk, false)
                        )->addText($targetedRisk, $this->boldFont, $this->centerParagraph);
                        $table->addCell(PhpWord\Shared\Converter::cmToTwip($opLastReviewDateWidth), $this->vAlignCenterCell)
                            ->addText(_WT($r['lastReviewDate']), $this->normalFont, $this->centerParagraph);
                        $table->addCell(
                            PhpWord\Shared\Converter::cmToTwip($opResidualDecisionWidth),
                            $this->vAlignCenterCell
                        )->addText(_WT($r['residualRiskAcceptance']), $this->normalFont, $opResidualDecisionParagraph);
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
        $isPdfOutput = $this->currentOutputFormat === self::OUTPUT_FORMAT_PDF;
        $opRisksAllScales = $this->operationalRiskScaleService->getOperationalRiskScales($this->anr);
        $opRisksImpactsScaleType = array_values(
            array_filter($opRisksAllScales, static function ($scale) {
                return $scale['type'] === OperationalRiskScaleSuperClass::TYPE_IMPACT;
            })
        );
        $opRisksImpactsScales = array_filter($opRisksImpactsScaleType[0]['scaleTypes'], static function ($scale) {
            return $scale['isHidden'] === false;
        });

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
                $assetWidth = $isPdfOutput ? 3.80 : 3.40;
                $riskSourceWidth = $isPdfOutput ? 1.90 : 1.80;
                $impactWidth = $isPdfOutput ? 0.70 : 0.70;
                $threatLabelWidth = $isPdfOutput ? 2.70 : 2.60;
                $threatRateWidth = $isPdfOutput ? 1.00 : 0.90;
                $vulnerabilityLabelWidth = $isPdfOutput ? 3.00 : 3.60;
                $existingControlsWidth = $isPdfOutput ? 3.10 : 3.40;
                $vulnerabilityRateWidth = $isPdfOutput ? 1.50 : 1.60;
                $currentRiskWidth = $isPdfOutput ? 0.80 : 0.90;
                $targetRiskWidth = $isPdfOutput ? 1.70 : 1.60;
                $reviewWidth = $isPdfOutput ? 5.00 : 5.40;
                $section->addText(
                    $this->anrTranslate(InstanceRiskSuperClass::getTreatmentNameByType($i)),
                    $this->titleFont,
                    ['alignment' => 'left', 'spaceAfter' => 120]
                );

                $tableRiskInfo = $section->addTable(array_merge($this->borderTable, [
                    'layout' => PhpWord\Style\Table::LAYOUT_FIXED,
                ]));

                $tableRiskInfo->addRow(400, $this->tblHeader);
                $tableRiskInfo->addCell(
                    PhpWord\Shared\Converter::cmToTwip($assetWidth),
                    $this->restartAndGrayCell
                )->addText($this->anrTranslate('Asset'), $this->boldFont, $this->centerParagraph);
                $tableRiskInfo->addCell(
                    PhpWord\Shared\Converter::cmToTwip($riskSourceWidth),
                    $this->restartAndGrayCell
                )->addText($this->anrTranslate('Risk source'), $this->boldFont, $this->centerParagraph);
                $tableRiskInfo->addCell(
                    PhpWord\Shared\Converter::cmToTwip($impactWidth * 3),
                    $this->setColSpanCell(3, 'DFDFDF')
                )->addText($this->anrTranslate('Impact'), $this->boldFont, $this->centerParagraph);
                $tableRiskInfo->addCell(
                    PhpWord\Shared\Converter::cmToTwip($threatLabelWidth + $threatRateWidth),
                    $this->setColSpanCell(2, 'DFDFDF')
                )->addText($this->anrTranslate('Threat'), $this->boldFont, $this->centerParagraph);
                $tableRiskInfo->addCell(
                    PhpWord\Shared\Converter::cmToTwip(
                        $vulnerabilityLabelWidth + $existingControlsWidth + $vulnerabilityRateWidth
                    ),
                    $this->setColSpanCell(3, 'DFDFDF')
                )->addText($this->anrTranslate('Vulnerability'), $this->boldFont, $this->centerParagraph);
                $tableRiskInfo->addCell(
                    PhpWord\Shared\Converter::cmToTwip($currentRiskWidth * 3),
                    $this->setColSpanCell(3, 'DFDFDF')
                )->addText($this->anrTranslate('Current risk'), $this->boldFont, $this->centerParagraph);
                $tableRiskInfo->addCell(
                    PhpWord\Shared\Converter::cmToTwip($targetRiskWidth),
                    $this->restartAndGrayCell
                )->addText($this->anrTranslate('Residual risk'), $this->boldFont, $this->centerParagraph);
                $tableRiskInfo->addCell(
                    PhpWord\Shared\Converter::cmToTwip($reviewWidth),
                    $this->restartAndGrayCell
                )->addText(
                    $this->anrTranslate('Review / residual risk acceptance'),
                    $this->boldFont,
                    $this->centerParagraph
                );
                $tableRiskInfo->addRow(400, $this->tblHeader);
                $tableRiskInfo->addCell(
                    PhpWord\Shared\Converter::cmToTwip($assetWidth),
                    $this->continueAndGrayCell
                );
                $tableRiskInfo->addCell(
                    PhpWord\Shared\Converter::cmToTwip($riskSourceWidth),
                    $this->continueAndGrayCell
                );
                $tableRiskInfo->addCell(PhpWord\Shared\Converter::cmToTwip($impactWidth), $this->grayCell)
                    ->addText('C', $this->boldFont, $this->centerParagraph);
                $tableRiskInfo->addCell(PhpWord\Shared\Converter::cmToTwip($impactWidth), $this->grayCell)
                    ->addText('I', $this->boldFont, $this->centerParagraph);
                $tableRiskInfo->addCell(PhpWord\Shared\Converter::cmToTwip($impactWidth), $this->grayCell)
                    ->addText($this->anrTranslate('A'), $this->boldFont, $this->centerParagraph);
                $tableRiskInfo->addCell(
                    PhpWord\Shared\Converter::cmToTwip($threatLabelWidth),
                    $this->grayCell
                )->addText($this->anrTranslate('Label'), $this->boldFont, $this->centerParagraph);
                $tableRiskInfo->addCell(
                    PhpWord\Shared\Converter::cmToTwip($threatRateWidth),
                    $this->grayCell
                )->addText($this->anrTranslate('Prob.'), $this->boldFont, $this->centerParagraph);
                $tableRiskInfo->addCell(
                    PhpWord\Shared\Converter::cmToTwip($vulnerabilityLabelWidth),
                    $this->grayCell
                )->addText($this->anrTranslate('Label'), $this->boldFont, $this->centerParagraph);
                $tableRiskInfo->addCell(
                    PhpWord\Shared\Converter::cmToTwip($existingControlsWidth),
                    $this->grayCell
                )->addText($this->anrTranslate('Existing controls'), $this->boldFont, $this->centerParagraph);
                $tableRiskInfo->addCell(
                    PhpWord\Shared\Converter::cmToTwip($vulnerabilityRateWidth),
                    $this->grayCell
                )->addText($this->anrTranslate('Qualif.'), $this->boldFont, $this->centerParagraph);
                $tableRiskInfo->addCell(PhpWord\Shared\Converter::cmToTwip($currentRiskWidth), $this->grayCell)
                    ->addText('C', $this->boldFont, $this->centerParagraph);
                $tableRiskInfo->addCell(PhpWord\Shared\Converter::cmToTwip($currentRiskWidth), $this->grayCell)
                    ->addText('I', $this->boldFont, $this->centerParagraph);
                $tableRiskInfo->addCell(PhpWord\Shared\Converter::cmToTwip($currentRiskWidth), $this->grayCell)
                    ->addText($this->anrTranslate('A'), $this->boldFont, $this->centerParagraph);
                $tableRiskInfo->addCell(
                    PhpWord\Shared\Converter::cmToTwip($targetRiskWidth),
                    $this->continueAndGrayCell
                );
                $tableRiskInfo->addCell(
                    PhpWord\Shared\Converter::cmToTwip($reviewWidth),
                    $this->continueAndGrayCell
                );

                $impacts = ['c', 'i', 'd'];
                foreach ($risksByTreatment as $r) {
                    foreach ($impacts as $impact) {
                        if ($r[$impact . '_risk_enabled'] === 0) {
                            $r[$impact . '_risk'] = null;
                        }
                    }
                    foreach ($r as $key => $value) {
                        if ($this->isUnavailableDisplayValue($value)) {
                            $r[$key] = '-';
                        }
                    }
                    /** @var Entity\Instance $instance */
                    $instance = $this->instanceTable->findByIdAndAnr($r['instance'], $this->anr);
                    if ($instance->getObject()->isScopeGlobal()) {
                        $path = $instance->getName($this->currentLangAnrIndex)
                            . ' (' . $this->anrTranslate('Global') . ')';
                    } else {
                        $path = $instance->getHierarchyString();
                    }

                    $tableRiskInfo->addRow(400);
                    $tableRiskInfo->addCell(PhpWord\Shared\Converter::cmToTwip($assetWidth), $this->vAlignCenterCell)
                        ->addText(_WT($path), $this->normalFont, $this->leftParagraph);
                    $tableRiskInfo->addCell(
                        PhpWord\Shared\Converter::cmToTwip($riskSourceWidth),
                        $this->vAlignCenterCell
                    )
                        ->addText(
                            _WT($r['riskSourceLabel'] !== '' ? $r['riskSourceLabel'] : '-'),
                            $this->normalFont,
                            $this->leftParagraph
                        );
                    $tableRiskInfo->addCell(PhpWord\Shared\Converter::cmToTwip($impactWidth), $this->vAlignCenterCell)
                        ->addText((string)$r['c_impact'], $this->normalFont, $this->centerParagraph);
                    $tableRiskInfo->addCell(PhpWord\Shared\Converter::cmToTwip($impactWidth), $this->vAlignCenterCell)
                        ->addText((string)$r['i_impact'], $this->normalFont, $this->centerParagraph);
                    $tableRiskInfo->addCell(PhpWord\Shared\Converter::cmToTwip($impactWidth), $this->vAlignCenterCell)
                        ->addText((string)$r['d_impact'], $this->normalFont, $this->centerParagraph);
                    $tableRiskInfo->addCell(
                        PhpWord\Shared\Converter::cmToTwip($threatLabelWidth),
                        $this->vAlignCenterCell
                    )
                        ->addText(
                            _WT($this->buildInfoRiskThreatSummary($r)),
                            $this->normalFont,
                            $this->leftParagraph
                        );
                    $tableRiskInfo->addCell(
                        PhpWord\Shared\Converter::cmToTwip($threatRateWidth),
                        $this->vAlignCenterCell
                    )->addText(
                        $this->formatDisplayValue($r['threatRate'] ?? null),
                        $this->normalFont,
                        $this->centerParagraph
                    );
                    $tableRiskInfo->addCell(
                        PhpWord\Shared\Converter::cmToTwip($vulnerabilityLabelWidth),
                        $this->vAlignCenterCell
                    )
                        ->addText(
                            _WT($this->buildInfoRiskVulnerabilitySummary($r)),
                            $this->normalFont,
                            $this->leftParagraph
                        );
                    $tableRiskInfo->addCell(
                        PhpWord\Shared\Converter::cmToTwip($existingControlsWidth),
                        $this->vAlignCenterCell
                    )->addText(
                        _WT($this->formatDisplayValue($r['comment'] ?? null)),
                        $this->normalFont,
                        $this->leftParagraph
                    );
                    $tableRiskInfo->addCell(
                        PhpWord\Shared\Converter::cmToTwip($vulnerabilityRateWidth),
                        $this->vAlignCenterCell
                    )->addText(
                        $this->formatDisplayValue($r['vulnerabilityRate'] ?? null),
                        $this->normalFont,
                        $this->centerParagraph
                    );
                    $tableRiskInfo->addCell(
                        PhpWord\Shared\Converter::cmToTwip($currentRiskWidth),
                        $this->setBgColorCell($r['c_risk'])
                    )->addText((string)$r['c_risk'], $this->boldFont, $this->centerParagraph);
                    $tableRiskInfo->addCell(
                        PhpWord\Shared\Converter::cmToTwip($currentRiskWidth),
                        $this->setBgColorCell($r['i_risk'])
                    )->addText((string)$r['i_risk'], $this->boldFont, $this->centerParagraph);
                    $tableRiskInfo->addCell(
                        PhpWord\Shared\Converter::cmToTwip($currentRiskWidth),
                        $this->setBgColorCell($r['d_risk'])
                    )->addText((string)$r['d_risk'], $this->boldFont, $this->centerParagraph);
                    $tableRiskInfo
                        ->addCell(
                            PhpWord\Shared\Converter::cmToTwip($targetRiskWidth),
                            $this->setBgColorCell($r['target_risk'])
                        )
                        ->addText($r['target_risk'], $this->boldFont, $this->centerParagraph);
                    $tableRiskInfo->addCell(PhpWord\Shared\Converter::cmToTwip($reviewWidth), $this->vAlignCenterCell)
                        ->addText(
                            _WT($this->buildInfoRiskReviewSummary($r)),
                            $this->normalFont,
                            $this->leftParagraph
                        );
                }
                $section->addTextBreak();
            }
            if (!empty($risksOpByTreatment)) {
                if (!$title) {
                    $section->addText(
                        $this->anrTranslate(InstanceRiskOpSuperClass::getTreatmentNameByType($i)),
                        $this->titleFont,
                        ['alignment' => 'left', 'spaceAfter' => 120]
                    );
                }
                $opRiskScaleCellWidth = $isPdfOutput ? 0.90 : 0.82;
                $opImpactHeaderHeight = $isPdfOutput
                    ? PhpWord\Shared\Converter::cmToTwip(1.20)
                    : PhpWord\Shared\Converter::cmToTwip(1.60);
                $opNetRiskScaleGroupWidth = count($opRisksImpactsScales) * $opRiskScaleCellWidth;
                $opAssetWidth = $isPdfOutput ? 3.20 : 3.00;
                $opRiskSourceWidth = $isPdfOutput ? 2.20 : 2.00;
                $opRiskDescriptionWidth = $isPdfOutput ? 4.80 : 4.40;
                $opInherentRiskWidth = $isPdfOutput ? 2.50 : 2.20;
                $opNetProbabilityWidth = $isPdfOutput ? 1.00 : 0.90;
                $opNetRiskWidth = $isPdfOutput ? 1.70 : 1.50;
                $opExistingControlsWidth = $isPdfOutput ? 4.40 : 4.20;
                $opResidualRiskWidth = $isPdfOutput ? 1.60 : 1.80;
                $opReviewWidth = $isPdfOutput ? 4.80 : 5.80;
                $tableRiskOp = $section->addTable(array_merge($this->borderTable, [
                    'layout' => PhpWord\Style\Table::LAYOUT_FIXED,
                ]));

                $tableRiskOp->addRow(400, $this->tblHeader);
                $tableRiskOp->addCell(
                    PhpWord\Shared\Converter::cmToTwip($opAssetWidth),
                    $this->restartAndGrayCell
                )
                    ->addText($this->anrTranslate('Asset'), $this->boldFont, $this->centerParagraph);
                $tableRiskOp->addCell(
                    PhpWord\Shared\Converter::cmToTwip($opRiskSourceWidth),
                    $this->restartAndGrayCell
                )
                    ->addText($this->anrTranslate('Risk source'), $this->boldFont, $this->centerParagraph);
                $tableRiskOp->addCell(
                    PhpWord\Shared\Converter::cmToTwip($opRiskDescriptionWidth),
                    $this->restartAndGrayCell
                )
                    ->addText($this->anrTranslate('Risk description'), $this->boldFont, $this->centerParagraph);
                if ($this->anr->showRolfBrut()) {
                    $tableRiskOp->addCell(
                        PhpWord\Shared\Converter::cmToTwip($opInherentRiskWidth),
                        $this->restartAndGrayCell
                    )
                        ->addText($this->anrTranslate('Inherent risk'), $this->boldFont, $this->centerParagraph);
                }
                $tableRiskOp->addCell(
                    PhpWord\Shared\Converter::cmToTwip(
                        $opNetProbabilityWidth + $opNetRiskScaleGroupWidth + $opNetRiskWidth
                    ),
                    $this->setColSpanCell(2 + count($opRisksImpactsScales), 'DFDFDF')
                )
                    ->addText($this->anrTranslate('Net risk'), $this->boldFont, $this->centerParagraph);
                $tableRiskOp->addCell(
                    PhpWord\Shared\Converter::cmToTwip($opExistingControlsWidth),
                    $this->restartAndGrayCell
                )
                    ->addText($this->anrTranslate('Existing controls'), $this->boldFont, $this->centerParagraph);
                $tableRiskOp->addCell(
                    PhpWord\Shared\Converter::cmToTwip($opResidualRiskWidth),
                    $this->restartAndGrayCell
                )
                    ->addText($this->anrTranslate('Residual risk'), $this->boldFont, $this->centerParagraph);
                $tableRiskOp->addCell(
                    PhpWord\Shared\Converter::cmToTwip($opReviewWidth),
                    $this->restartAndGrayCell
                )
                    ->addText(
                        $this->anrTranslate('Review / residual risk acceptance'),
                        $this->boldFont,
                        $this->centerParagraph
                    );
                $tableRiskOp->addRow(400, $this->tblHeader);
                $tableRiskOp->addCell(PhpWord\Shared\Converter::cmToTwip($opAssetWidth), $this->continueAndGrayCell);
                $tableRiskOp->addCell(
                    PhpWord\Shared\Converter::cmToTwip($opRiskSourceWidth),
                    $this->continueAndGrayCell
                );
                $tableRiskOp->addCell(
                    PhpWord\Shared\Converter::cmToTwip($opRiskDescriptionWidth),
                    $this->continueAndGrayCell
                );
                if ($this->anr->showRolfBrut()) {
                    $tableRiskOp->addCell(
                        PhpWord\Shared\Converter::cmToTwip($opInherentRiskWidth),
                        $this->continueAndGrayCell
                    );
                }
                $tableRiskOp->addCell(
                    PhpWord\Shared\Converter::cmToTwip($opNetProbabilityWidth),
                    $this->restartAndGrayCell
                )
                    ->addText($this->anrTranslate('Prob.'), $this->boldFont, $this->centerParagraph);
                $tableRiskOp->addCell(
                    PhpWord\Shared\Converter::cmToTwip($opNetRiskScaleGroupWidth),
                    $this->setColSpanCell(count($opRisksImpactsScales), 'DFDFDF')
                )
                    ->addText($this->anrTranslate('Impact'), $this->boldFont, $this->centerParagraph);
                $tableRiskOp->addCell(
                    PhpWord\Shared\Converter::cmToTwip($opNetRiskWidth),
                    $this->restartAndGrayCell
                )
                    ->addText($this->anrTranslate('Current risk'), $this->boldFont, $this->centerParagraph);
                $tableRiskOp->addCell(
                    PhpWord\Shared\Converter::cmToTwip($opExistingControlsWidth),
                    $this->continueAndGrayCell
                );
                $tableRiskOp->addCell(
                    PhpWord\Shared\Converter::cmToTwip($opResidualRiskWidth),
                    $this->continueAndGrayCell
                );
                $tableRiskOp->addCell(
                    PhpWord\Shared\Converter::cmToTwip($opReviewWidth),
                    $this->continueAndGrayCell
                );
                $tableRiskOp->addRow($opImpactHeaderHeight, $this->tblHeader);
                $tableRiskOp->addCell(PhpWord\Shared\Converter::cmToTwip($opAssetWidth), $this->continueAndGrayCell);
                $tableRiskOp->addCell(
                    PhpWord\Shared\Converter::cmToTwip($opRiskSourceWidth),
                    $this->continueAndGrayCell
                );
                $tableRiskOp->addCell(
                    PhpWord\Shared\Converter::cmToTwip($opRiskDescriptionWidth),
                    $this->continueAndGrayCell
                );
                if ($this->anr->showRolfBrut()) {
                    $tableRiskOp->addCell(
                        PhpWord\Shared\Converter::cmToTwip($opInherentRiskWidth),
                        $this->continueAndGrayCell
                    );
                }
                $tableRiskOp->addCell(
                    PhpWord\Shared\Converter::cmToTwip($opNetProbabilityWidth),
                    $this->continueAndGrayCell
                );
                foreach ($opRisksImpactsScales as $opRiskImpactScale) {
                    $label = mb_substr(_WT($opRiskImpactScale['label']), 0, 3) . '.';
                    $tableRiskOp->addCell(
                        PhpWord\Shared\Converter::cmToTwip($opRiskScaleCellWidth),
                        array_merge($this->rotate90TextCell, ['bgcolor' => 'DFDFDF'])
                    )->addText($label, $this->boldFont, $this->verticalCenterParagraph);
                }
                $tableRiskOp->addCell(
                    PhpWord\Shared\Converter::cmToTwip($opNetRiskWidth),
                    $this->continueAndGrayCell
                );
                $tableRiskOp->addCell(
                    PhpWord\Shared\Converter::cmToTwip($opExistingControlsWidth),
                    $this->continueAndGrayCell
                );
                $tableRiskOp->addCell(
                    PhpWord\Shared\Converter::cmToTwip($opResidualRiskWidth),
                    $this->continueAndGrayCell
                );
                $tableRiskOp->addCell(
                    PhpWord\Shared\Converter::cmToTwip($opReviewWidth),
                    $this->continueAndGrayCell
                );

                foreach ($risksOpByTreatment as $r) {
                    $scalesData = [];
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
                        if ($this->isUnavailableDisplayValue($value)) {
                            $r[$key] = '-';
                        }
                    }

                    /** @var Entity\Instance $instance */
                    $instance = $this->instanceTable->findByIdAndAnr($r['instanceInfos']['id'], $this->anr);
                    $path = $instance->getHierarchyString();
                    $opRiskSourceLabel = $r['riskSourceLabel'] !== '' ? $r['riskSourceLabel'] : '-';
                    $opRiskSourceParagraph = $opRiskSourceLabel === '-'
                        ? $this->centerParagraph
                        : $this->leftParagraph;

                    $tableRiskOp->addRow(400);
                    $tableRiskOp->addCell(PhpWord\Shared\Converter::cmToTwip($opAssetWidth), $this->vAlignCenterCell)
                        ->addText(_WT($path), $this->normalFont, $this->leftParagraph);
                    $tableRiskOp->addCell(
                        PhpWord\Shared\Converter::cmToTwip($opRiskSourceWidth),
                        $this->vAlignCenterCell
                    )
                        ->addText(_WT($opRiskSourceLabel), $this->normalFont, $opRiskSourceParagraph);
                    $tableRiskOp->addCell(
                        PhpWord\Shared\Converter::cmToTwip($opRiskDescriptionWidth),
                        $this->vAlignCenterCell
                    )
                        ->addText(
                            _WT($r['label' . $this->currentLangAnrIndex]),
                            $this->normalFont,
                            $this->leftParagraph
                        );
                    if ($this->anr->showRolfBrut()) {
                        $tableRiskOp->addCell(
                            PhpWord\Shared\Converter::cmToTwip($opInherentRiskWidth),
                            $this->vAlignCenterCell
                        )
                            ->addText(
                                _WT($this->buildOperationalRiskSummary(
                                    $r,
                                    $opRisksImpactsScales,
                                    'brutProb',
                                    'brutValue',
                                    'cacheBrutRisk'
                                )),
                                $this->normalFont,
                                $this->leftParagraph
                            );
                    }
                    $tableRiskOp->addCell(
                        PhpWord\Shared\Converter::cmToTwip($opNetProbabilityWidth),
                        $this->vAlignCenterCell
                    )->addText($r['netProb'], $this->normalFont, $this->centerParagraph);
                    foreach ($opRisksImpactsScales as $opRiskImpactScale) {
                        $tableRiskOp->addCell(
                            PhpWord\Shared\Converter::cmToTwip($opRiskScaleCellWidth),
                            $this->vAlignCenterCell
                        )->addText(
                            $r['scales'][$opRiskImpactScale['id']]['netValue'],
                            $this->normalFont,
                            $this->centerParagraph
                        );
                    }
                    $tableRiskOp->addCell(
                        PhpWord\Shared\Converter::cmToTwip($opNetRiskWidth),
                        $this->setBgColorCell($r['cacheNetRisk'], false)
                    )->addText($r['cacheNetRisk'], $this->boldFont, $this->centerParagraph);
                    $tableRiskOp->addCell(
                        PhpWord\Shared\Converter::cmToTwip($opExistingControlsWidth),
                        $this->vAlignCenterCell
                    )
                        ->addText(_WT($r['comment']), $this->normalFont, $this->leftParagraph);
                    $cacheTargetedRisk = $r['cacheTargetedRisk'] === '-'
                        ? $r['cacheNetRisk']
                        : $r['cacheTargetedRisk'];
                    $tableRiskOp->addCell(
                        PhpWord\Shared\Converter::cmToTwip($opResidualRiskWidth),
                        $this->setBgColorCell($cacheTargetedRisk, false)
                    )->addText($cacheTargetedRisk, $this->boldFont, $this->centerParagraph);
                    $tableRiskOp->addCell(
                        PhpWord\Shared\Converter::cmToTwip($opReviewWidth),
                        $this->vAlignCenterCell
                    )->addText(
                        _WT($this->buildOperationalRiskReviewSummary($r)),
                        $this->normalFont,
                        $this->leftParagraph
                    );
                }
                $section->addTextBreak();
            }
            $result .= $this->getWordXmlFromWordObject($tableWord);
        }

        return $result;
    }

    private function buildInfoRiskThreatSummary(array $instanceRisk): string
    {
        return (string)($instanceRisk['threatLabel' . $this->currentLangAnrIndex] ?? '-');
    }

    private function buildInfoRiskVulnerabilitySummary(array $instanceRisk): string
    {
        return (string)($instanceRisk['vulnLabel' . $this->currentLangAnrIndex] ?? '-');
    }

    private function buildInfoRiskReviewSummary(array $instanceRisk): string
    {
        $reviewDate = !empty($instanceRisk['lastReviewDate']) ? $instanceRisk['lastReviewDate'] : '-';

        return implode("\n", [
            $this->anrTranslate('Last review date') . ': ' . $reviewDate,
            $this->formatResidualRiskAcceptanceFromArray($instanceRisk),
        ]);
    }

    private function buildOperationalRiskSummary(
        array $instanceRisk,
        array $impactScales,
        string $probabilityKey,
        string $valueKey,
        string $riskKey
    ): string {
        $lines = [
            $this->anrTranslate('Prob.') . ': ' . $this->formatDisplayValue($instanceRisk[$probabilityKey] ?? null),
        ];

        foreach ($impactScales as $impactScale) {
            $label = mb_substr((string)$impactScale['label'], 0, 3) . '.';
            $lines[] = $label . ': ' . $this->formatDisplayValue(
                $instanceRisk['scales'][$impactScale['id']][$valueKey] ?? null
            );
        }

        $lines[] = $this->anrTranslate('Current risk') . ': '
            . $this->formatDisplayValue($instanceRisk[$riskKey] ?? null);

        return implode("\n", $lines);
    }

    private function buildOperationalRiskReviewSummary(array $instanceRisk): string
    {
        $reviewDate = !empty($instanceRisk['lastReviewDate']) ? $instanceRisk['lastReviewDate'] : '-';
        $residualRiskAcceptance = $this->formatDisplayValue($instanceRisk['residualRiskAcceptance'] ?? null);

        return implode("\n", [
            $this->anrTranslate('Last review date') . ': ' . $reviewDate,
            $residualRiskAcceptance,
        ]);
    }

    private function formatDisplayValue(mixed $value): string
    {
        if ($value === null || $value === '' || $this->isUnavailableDisplayValue($value)) {
            return '-';
        }

        return (string)$value;
    }

    private function isUnavailableDisplayValue(mixed $value): bool
    {
        return $value === -1 || $value === '-1';
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

            $table->addRow(400, $this->tblHeader);
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
            if ($instanceRisk !== null
                && $recommendationRisk->getThreat() !== null
                && $recommendationRisk->getVulnerability() !== null
            ) {
                $riskConfidentiality = null;
                $riskAvailability = null;
                $riskIntegrity = null;
                if ($recommendationRisk->getThreat()->getConfidentiality()) {
                    $riskConfidentiality = $this->formatDisplayValue($instanceRisk->getRiskConfidentiality());
                }
                if ($recommendationRisk->getThreat()->getIntegrity()) {
                    $riskIntegrity = $this->formatDisplayValue($instanceRisk->getRiskIntegrity());
                }
                if ($recommendationRisk->getThreat()->getAvailability()) {
                    $riskAvailability = $this->formatDisplayValue($instanceRisk->getRiskAvailability());
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
                            _WT($recommendationRisk->getVulnerability()->getLabel($this->currentLangAnrIndex)),
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

        $isPdfOutput = $this->currentOutputFormat === self::OUTPUT_FORMAT_PDF;
        $recommendationWidth = $isPdfOutput ? 9.00 : 10.00;
        $importanceWidth = $isPdfOutput ? 1.40 : 2.00;
        $commentWidth = $isPdfOutput ? 3.60 : 5.00;
        $managerWidth = $isPdfOutput ? 3.00 : 4.00;
        $deadlineWidth = $isPdfOutput ? 2.20 : 3.00;

        $tableWord = new PhpWord\PhpWord();
        $section = $tableWord->addSection();
        $table = $section->addTable(
            $isPdfOutput
                ? array_merge($this->borderTable, [
                    'layout' => PhpWord\Style\Table::LAYOUT_FIXED,
                    'align' => 'center',
                    'cellMarginLeft' => 55,
                    'cellMarginRight' => 55,
                ])
                : $this->borderTable
        );

        if (!empty($recommendationRisks)) {
            $table->addRow(400, $this->tblHeader);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip($recommendationWidth), $this->grayCell)
                ->addText($this->anrTranslate('Recommendation'), $this->boldFont, $this->centerParagraph);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip($importanceWidth), $this->grayCell)
                ->addText($this->anrTranslate('Imp.'), $this->boldFont, $this->centerParagraph);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip($commentWidth), $this->grayCell)
                ->addText($this->anrTranslate('Comment'), $this->boldFont, $this->centerParagraph);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip($managerWidth), $this->grayCell)
                ->addText($this->anrTranslate('Manager'), $this->boldFont, $this->centerParagraph);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip($deadlineWidth), $this->grayCell)
                ->addText($this->anrTranslate('Deadline'), $this->boldFont, $this->centerParagraph);
        }

        $globalObjectsRecommendationsKeys = [];
        $processedRecommendationsUuids = [];
        foreach ($recommendationRisks as $recommendationRisk) {
            $recommendation = $recommendationRisk->getRecommendation();
            if (isset($processedRecommendationsUuids[$recommendation->getUuid()])) {
                continue;
            }
            $processedRecommendationsUuids[$recommendation->getUuid()] = true;
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
            $cellRecoName = $table->addCell(
                PhpWord\Shared\Converter::cmToTwip($recommendationWidth),
                $this->vAlignCenterCell
            );
            $cellRecoNameRun = $cellRecoName->addTextRun($this->leftParagraph);
            $this->addMultilineTextRunText($cellRecoNameRun, $recommendation->getCode(), $this->boldFont, 1);
            $this->addMultilineTextRunText($cellRecoNameRun, $recommendation->getDescription(), $this->normalFont);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip($importanceWidth), $this->vAlignCenterCell)
                ->addText($importance, $this->redFont, $this->centerParagraph);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip($commentWidth), $this->vAlignCenterCell)
                ->addText(_WT($recommendation->getComment()), $this->normalFont, $this->leftParagraph);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip($managerWidth), $this->vAlignCenterCell)
                ->addText(_WT($recommendation->getResponsible()), $this->normalFont, $this->centerParagraph);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip($deadlineWidth), $this->vAlignCenterCell)
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

        $isPdfOutput = $this->currentOutputFormat === self::OUTPUT_FORMAT_PDF;
        $creatorWidth = $isPdfOutput ? 2.80 : 3.00;
        $recommendationWidth = $isPdfOutput ? 7.00 : 6.00;
        $riskWidth = $isPdfOutput ? 9.50 : 8.00;
        $commentWidth = $isPdfOutput ? 3.80 : 4.50;
        $riskBeforeWidth = $isPdfOutput ? 1.70 : 1.75;
        $riskAfterWidth = $isPdfOutput ? 1.70 : 1.75;

        $tableWord = new PhpWord\PhpWord();
        $section = $tableWord->addSection();
        $table = $section->addTable(
            $isPdfOutput
                ? array_merge($this->borderTable, [
                    'layout' => PhpWord\Style\Table::LAYOUT_FIXED,
                    'align' => 'center',
                    'cellMarginLeft' => 55,
                    'cellMarginRight' => 55,
                ])
                : $this->borderTable
        );

        if ($recoRecords) {
            $table->addRow(400, $this->tblHeader);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip($creatorWidth), $this->grayCell)
                ->addText($this->anrTranslate('By'), $this->boldFont, $this->centerParagraph);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip($recommendationWidth), $this->grayCell)
                ->addText($this->anrTranslate('Recommendation'), $this->boldFont, $this->centerParagraph);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip($riskWidth), $this->grayCell)
                ->addText($this->anrTranslate('Risk'), $this->boldFont, $this->centerParagraph);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip($commentWidth), $this->grayCell)
                ->addText($this->anrTranslate('Implementation comment'), $this->boldFont, $this->centerParagraph);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip($riskBeforeWidth), $this->grayCell)
                ->addText($this->anrTranslate('Risk before'), $this->boldFont, $this->centerParagraph);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip($riskAfterWidth), $this->grayCell)
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
            } elseif ($this->isUnavailableDisplayValue($riskMaxBefore)) {
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
            } elseif ($this->isUnavailableDisplayValue($riskMaxAfter)) {
                $riskMaxAfter = '-';
                $bgcolorRiskAfter = 'FFFFFF';
            }
            $styleContentCellRiskAfter = ['valign' => 'center', 'bgcolor' => $bgcolorRiskAfter];

            $table->addRow(400);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip($creatorWidth), $this->vAlignCenterCell)
                ->addText(_WT($recoRecord->getCreator()), $this->normalFont, $this->leftParagraph);
            $cellReco = $table->addCell(PhpWord\Shared\Converter::cmToTwip($recommendationWidth), $this->vAlignCenterCell);
            $cellRecoRun = $cellReco->addTextRun($this->leftParagraph);
            $cellRecoRun->addText($importance . ' ', $this->redFont);
            $this->addMultilineTextRunText($cellRecoRun, $recoRecord->getRecoCode(), $this->boldFont, 1);
            $this->addMultilineTextRunText($cellRecoRun, $recoRecord->getRecoDescription(), $this->normalFont, 2);
            $this->addLabeledTextRunLine(
                $cellRecoRun,
                $this->anrTranslate('Comment') . ': ',
                $recoRecord->getRecoComment(),
                $this->boldFont,
                $this->normalFont
            );
            $this->addLabeledTextRunLine(
                $cellRecoRun,
                $this->anrTranslate('Deadline') . ': ',
                $recoDeadline,
                $this->boldFont,
                $this->normalFont
            );
            $this->addLabeledTextRunLine(
                $cellRecoRun,
                $this->anrTranslate('Validation date') . ': ',
                $recoValidationDate,
                $this->boldFont,
                $this->normalFont
            );
            $this->addLabeledTextRunLine(
                $cellRecoRun,
                $this->anrTranslate('Manager') . ': ',
                $recoRecord->getRecoResponsable(),
                $this->boldFont,
                $this->normalFont,
                0
            );
            $cellRisk = $table->addCell(PhpWord\Shared\Converter::cmToTwip($riskWidth), $this->vAlignCenterCell);
            $cellRiskRun = $cellRisk->addTextRun($this->leftParagraph);
            $this->addLabeledTextRunLine(
                $cellRiskRun,
                $this->anrTranslate('Asset type') . ': ',
                $recoRecord->getRiskAsset(),
                $this->boldFont,
                $this->normalFont
            );
            $this->addLabeledTextRunLine(
                $cellRiskRun,
                $this->anrTranslate('Asset') . ': ',
                $recoRecord->getRiskInstance(),
                $this->boldFont,
                $this->normalFont
            );
            $this->addLabeledTextRunLine(
                $cellRiskRun,
                $this->anrTranslate('Threat') . ': ',
                $recoRecord->getRiskThreat(),
                $this->boldFont,
                $this->normalFont
            );
            $this->addLabeledTextRunLine(
                $cellRiskRun,
                $this->anrTranslate('Vulnerability') . ': ',
                $recoRecord->getRiskVul(),
                $this->boldFont,
                $this->normalFont
            );
            $this->addLabeledTextRunLine(
                $cellRiskRun,
                $this->anrTranslate('Treatment type') . ': ',
                $this->anrTranslate(InstanceRiskSuperClass::getTreatmentNameByType(
                    $recoRecord->getRiskKindOfMeasure()
                )),
                $this->boldFont,
                $this->normalFont
            );
            $this->addLabeledTextRunLine(
                $cellRiskRun,
                $this->anrTranslate('Existing controls') . ': ',
                $recoRecord->getRiskCommentBefore(),
                $this->boldFont,
                $this->normalFont
            );
            $this->addLabeledTextRunLine(
                $cellRiskRun,
                $this->anrTranslate('New controls') . ': ',
                $recoRecord->getRiskCommentAfter(),
                $this->boldFont,
                $this->normalFont,
                0
            );
            $table->addCell(PhpWord\Shared\Converter::cmToTwip($commentWidth), $this->vAlignCenterCell)
                ->addText(_WT($recoRecord->getImplComment()), $this->normalFont, $this->leftParagraph);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip($riskBeforeWidth), $styleContentCellRiskBefore)
                ->addText($riskMaxBefore, $this->boldFont, $this->centerParagraph);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip($riskAfterWidth), $styleContentCellRiskAfter)
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

            $complianceLevel = '';
            $bgcolor = 'FFFFFF';

            $soaScaleComment = $soa->getSoaScaleComment();
            if ($soaScaleComment !== null && !$soaScaleComment->isHidden()) {
                $complianceLevel = $soaScaleComment->getComment();
                $bgcolor = $soaScaleComment->getColour();
            }

            if ($soa->getEx()) {
                $complianceLevel = '';
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
                    [
                        'rolfRisks' => $rolfRiskIds,
                        'limit' => -1,
                        'order' => 'cacheNetRisk',
                        'order_direction' => 'desc',
                    ]
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
                        $tableRiskInfo->addCell(PhpWord\Shared\Converter::cmToTwip(2.50), $this->restartAndGrayCell)
                            ->addText($this->anrTranslate('Risk source'), $this->boldFont, $this->centerParagraph);
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
                        $tableRiskInfo->addCell(PhpWord\Shared\Converter::cmToTwip(2.00), $this->restartAndGrayCell)
                            ->addText($this->anrTranslate('Last review date'), $this->boldFont, $this->centerParagraph);
                        $tableRiskInfo->addCell(PhpWord\Shared\Converter::cmToTwip(4.50), $this->restartAndGrayCell)
                            ->addText(
                                $this->anrTranslate('Residual risk decision'),
                                $this->boldFont,
                                $this->centerParagraph
                            );
                        $tableRiskInfo->addRow(400);
                        $tableRiskInfo->addCell(PhpWord\Shared\Converter::cmToTwip(3.00), $this->continueAndGrayCell);
                        $tableRiskInfo->addCell(PhpWord\Shared\Converter::cmToTwip(2.50), $this->continueAndGrayCell);
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
                        $tableRiskInfo->addCell(PhpWord\Shared\Converter::cmToTwip(2.00), $this->continueAndGrayCell);
                        $tableRiskInfo->addCell(PhpWord\Shared\Converter::cmToTwip(4.50), $this->continueAndGrayCell);
                    }
                    if (!empty($operationalInstanceRisks)) {
                        $section->addText($this->anrTranslate('Operational risks'), $this->boldFont);
                        $tableRiskOp = $section->addTable($this->borderTable);

                        $tableRiskOp->addRow(400);
                        $tableRiskOp->addCell(PhpWord\Shared\Converter::cmToTwip(3.00), $this->restartAndGrayCell)
                            ->addText($this->anrTranslate('Asset'), $this->boldFont, $this->centerParagraph);
                        $tableRiskOp->addCell(PhpWord\Shared\Converter::cmToTwip(2.50), $this->restartAndGrayCell)
                            ->addText($this->anrTranslate('Risk source'), $this->boldFont, $this->centerParagraph);
                        $tableRiskOp->addCell(PhpWord\Shared\Converter::cmToTwip(7.50), $this->restartAndGrayCell)
                            ->addText(
                                $this->anrTranslate('Risk description'),
                                $this->boldFont,
                                $this->centerParagraph
                            );
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
                        $tableRiskOp->addCell(PhpWord\Shared\Converter::cmToTwip(2.50), $this->continueAndGrayCell);
                        $tableRiskOp->addCell(PhpWord\Shared\Converter::cmToTwip(7.50), $this->continueAndGrayCell);
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
                        $tableRiskOp->addCell(PhpWord\Shared\Converter::cmToTwip(2.50), $this->continueAndGrayCell);
                        $tableRiskOp->addCell(PhpWord\Shared\Converter::cmToTwip(7.50), $this->continueAndGrayCell);
                        if ($this->anr->showRolfBrut()) {
                            $tableRiskOp->addCell(PhpWord\Shared\Converter::cmToTwip(1.00), $this->continueAndGrayCell);
                            foreach ($opRisksImpactsScales as $opRiskImpactScale) {
                                $label = mb_substr(_WT($opRiskImpactScale['label']), 0, 3) . '.';
                                $tableRiskOp->addCell(
                                    PhpWord\Shared\Converter::cmToTwip(0.70),
                                    array_merge($this->rotate90TextCell, ['bgcolor' => 'DFDFDF'])
                                )->addText($label, $this->boldFont, $this->verticalCenterParagraph);
                            }
                            $tableRiskOp->addCell(
                                PhpWord\Shared\Converter::cmToTwip(1.00),
                                $this->continueAndGrayCell
                            );
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
                            if ($this->isUnavailableDisplayValue($value)) {
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
                        $tableRiskInfo->addCell(PhpWord\Shared\Converter::cmToTwip(2.50), $this->vAlignCenterCell)
                            ->addText(
                                _WT($instanceRisk['riskSourceLabel'] !== '' ? $instanceRisk['riskSourceLabel'] : '-'),
                                $this->normalFont,
                                $this->leftParagraph
                            );
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
                            ->addText($this->anrTranslate(
                                InstanceRiskOpSuperClass::getTreatmentNameByType($instanceRisk['kindOfMeasure'])
                            ), $this->normalFont, $this->leftParagraph);
                        $tableRiskInfo->addCell(
                            PhpWord\Shared\Converter::cmToTwip(1.50),
                            $this->setBgColorCell($instanceRisk['target_risk'])
                        )->addText($instanceRisk['target_risk'], $this->boldFont, $this->centerParagraph);
                        $tableRiskInfo->addCell(PhpWord\Shared\Converter::cmToTwip(2.00), $this->vAlignCenterCell)
                            ->addText(
                                _WT($instanceRisk['lastReviewDate'] !== '' ? $instanceRisk['lastReviewDate'] : '-'),
                                $this->normalFont,
                                $this->centerParagraph
                            );
                        $tableRiskInfo->addCell(PhpWord\Shared\Converter::cmToTwip(4.50), $this->vAlignCenterCell)
                            ->addText(
                                _WT($this->formatResidualRiskAcceptanceFromArray($instanceRisk)),
                                $this->normalFont,
                                $this->leftParagraph
                            );
                    }
                }

                if (!empty($operationalInstanceRisks)) {
                    foreach ($operationalInstanceRisks as $riskOp) {
                        foreach ($riskOp as $key => $value) {
                            if ($this->isUnavailableDisplayValue($value)) {
                                $riskOp[$key] = '-';
                            }
                        }

                        $instance = $this->instanceTable->findById($riskOp['instanceInfos']['id']);
                        $path = $instance->getHierarchyString();

                        $tableRiskOp->addRow(400);
                        $tableRiskOp->addCell(PhpWord\Shared\Converter::cmToTwip(3.00), $this->vAlignCenterCell)
                            ->addText(_WT($path), $this->normalFont, $this->leftParagraph);
                        $tableRiskOp->addCell(PhpWord\Shared\Converter::cmToTwip(2.50), $this->vAlignCenterCell)
                            ->addText(
                                _WT($riskOp['riskSourceLabel'] !== '' ? $riskOp['riskSourceLabel'] : '-'),
                                $this->normalFont,
                                $this->leftParagraph
                            );
                        $tableRiskOp->addCell(PhpWord\Shared\Converter::cmToTwip(7.50), $this->vAlignCenterCell)
                            ->addText(
                                _WT($riskOp['label' . $this->currentLangAnrIndex]),
                                $this->normalFont,
                                $this->leftParagraph
                            );
                        if ($this->anr->showRolfBrut()) {
                            $tableRiskOp->addCell(PhpWord\Shared\Converter::cmToTwip(1.00), $this->vAlignCenterCell)
                                ->addText($riskOp['brutProb'], $this->normalFont, $this->centerParagraph);
                            foreach ($opRisksImpactsScales as $opRiskImpactScale) {
                                $tableRiskOp
                                    ->addCell(PhpWord\Shared\Converter::cmToTwip(0.70), $this->vAlignCenterCell)
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
                            ->addText($this->anrTranslate(
                                InstanceRiskOpSuperClass::getTreatmentNameByType($riskOp['kindOfMeasure'])
                            ), $this->normalFont, $this->leftParagraph);
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
        $isPdfOutput = $this->currentOutputFormat === self::OUTPUT_FORMAT_PDF;
        $actorLabelWidth = $isPdfOutput ? 4.50 : 6.00;
        $actorNameWidth = $isPdfOutput ? 5.75 : 6.00;
        $actorContactWidth = $isPdfOutput ? 5.75 : 6.00;

        //create section
        $tableWord = new PhpWord\PhpWord();
        $section = $tableWord->addSection();
        $table = $section->addTable(
            $isPdfOutput
                ? array_merge($this->borderTable, [
                    'layout' => PhpWord\Style\Table::LAYOUT_FIXED,
                    'align' => 'center',
                    'cellMarginLeft' => 55,
                    'cellMarginRight' => 55,
                ])
                : $this->borderTable
        );

        //header if array is not empty
        $table->addRow(400);
        $table->addCell(PhpWord\Shared\Converter::cmToTwip($actorLabelWidth), $this->grayCell)
            ->addText($this->anrTranslate('Actor'), $this->boldFont, $this->centerParagraph);
        $table->addCell(PhpWord\Shared\Converter::cmToTwip($actorNameWidth), $this->grayCell)
            ->addText($this->anrTranslate('Name'), $this->boldFont, $this->centerParagraph);
        $table->addCell(PhpWord\Shared\Converter::cmToTwip($actorContactWidth), $this->grayCell)
            ->addText($this->anrTranslate('Contact'), $this->boldFont, $this->centerParagraph);

        $table->addRow(400);
        $table->addCell(PhpWord\Shared\Converter::cmToTwip($actorLabelWidth), $this->grayCell)
            ->addText($this->anrTranslate('Controller'), $this->boldFont, $this->leftParagraph);
        $table->addCell(PhpWord\Shared\Converter::cmToTwip($actorNameWidth), $this->vAlignCenterCell)
            ->addText(
                _WT($record->getController() ? $record->getController()->getLabel() : ''),
                $this->normalFont,
                $this->leftParagraph
            );
        $table->addCell(PhpWord\Shared\Converter::cmToTwip($actorContactWidth), $this->vAlignCenterCell)
            ->addText(
                _WT($record->getController() ? $record->getController()->getContact() : ''),
                $this->normalFont,
                $this->leftParagraph
            );

        $table->addRow(400);
        $table->addCell(PhpWord\Shared\Converter::cmToTwip($actorLabelWidth), $this->grayCell)
            ->addText($this->anrTranslate('Representative'), $this->boldFont, $this->leftParagraph);
        $table->addCell(PhpWord\Shared\Converter::cmToTwip($actorNameWidth), $this->vAlignCenterCell)
            ->addText(
                _WT($record->getRepresentative() ? $record->getRepresentative()->getLabel() : ''),
                $this->normalFont,
                $this->leftParagraph
            );
        $table->addCell(PhpWord\Shared\Converter::cmToTwip($actorContactWidth), $this->vAlignCenterCell)
            ->addText(
                _WT($record->getRepresentative() ? $record->getRepresentative()->getContact() : ''),
                $this->normalFont,
                $this->leftParagraph
            );

        $table->addRow(400);
        $table->addCell(PhpWord\Shared\Converter::cmToTwip($actorLabelWidth), $this->grayCell)
            ->addText($this->anrTranslate('Data protection officer'), $this->boldFont, $this->leftParagraph);
        $table->addCell(PhpWord\Shared\Converter::cmToTwip($actorNameWidth), $this->vAlignCenterCell)
            ->addText(
                _WT($record->getDpo() ? $record->getDpo()->getLabel() : ''),
                $this->normalFont,
                $this->leftParagraph
            );
        $table->addCell(PhpWord\Shared\Converter::cmToTwip($actorContactWidth), $this->vAlignCenterCell)
            ->addText(
                _WT($record->getDpo() ? $record->getDpo()->getContact() : ''),
                $this->normalFont,
                $this->leftParagraph
            );

        $table->addRow(400);
        $table->addCell(PhpWord\Shared\Converter::cmToTwip($actorLabelWidth), $this->grayCell)
            ->addText($this->anrTranslate('Joint controllers'), $this->boldFont, $this->leftParagraph);

        if (!empty($jointControllers)) {
            $i = 0;
            foreach ($jointControllers as $jc) {
                $table->addCell(PhpWord\Shared\Converter::cmToTwip($actorNameWidth), $this->vAlignCenterCell)
                    ->addText(_WT($jc->getLabel()), $this->normalFont, $this->leftParagraph);
                $table->addCell(PhpWord\Shared\Converter::cmToTwip($actorContactWidth), $this->vAlignCenterCell)
                    ->addText(_WT($jc->getContact()), $this->normalFont, $this->leftParagraph);
                if ($i !== count($jointControllers) - 1) {
                    $table->addRow(400);
                    $table->addCell(PhpWord\Shared\Converter::cmToTwip($actorLabelWidth), $this->grayCell);
                }
                ++$i;
            }
        } else {
            $table->addCell(PhpWord\Shared\Converter::cmToTwip($actorNameWidth), $this->vAlignCenterCell);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip($actorContactWidth), $this->vAlignCenterCell);
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
        $recordEntity = $this->recordTable->findById((int)$recordId);
        $personalData = $recordEntity->getPersonalData();

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
                foreach ($pd->getDataCategories() as $dc) {
                    $dataCategories .= $dc->getLabel() . "\n";
                }
                $retentionPeriod = $pd->getRetentionPeriod() . ' ';
                if ($pd->getRetentionPeriodMode() === 0) {
                    $retentionPeriod .= $this->anrTranslate('day(s)');
                } elseif ($pd->getRetentionPeriodMode() === 1) {
                    $retentionPeriod .= $this->anrTranslate('month(s)');
                } else {
                    $retentionPeriod .= $this->anrTranslate('year(s)');
                }
                $table->addCell(PhpWord\Shared\Converter::cmToTwip(3.60), $this->vAlignCenterCell)
                    ->addText(_WT($pd->getDataSubject()), $this->normalFont, $this->leftParagraph);
                $table->addCell(PhpWord\Shared\Converter::cmToTwip(3.60), $this->vAlignCenterCell)
                    ->addText(_WT($dataCategories), $this->normalFont, $this->leftParagraph);
                $table->addCell(PhpWord\Shared\Converter::cmToTwip(3.60), $this->vAlignCenterCell)
                    ->addText(_WT($pd->getDescription()), $this->normalFont, $this->leftParagraph);
                $table->addCell(PhpWord\Shared\Converter::cmToTwip(3.60), $this->vAlignCenterCell)
                    ->addText(_WT($retentionPeriod), $this->normalFont, $this->leftParagraph);
                $table->addCell(PhpWord\Shared\Converter::cmToTwip(3.60), $this->vAlignCenterCell)
                    ->addText(
                        _WT($pd->getRetentionPeriodDescription()),
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
        $recordEntity = $this->recordTable->findById((int)$recordId);
        $recipients = $recordEntity->getRecipients();

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
                    ->addText(_WT($r->getLabel()), $this->normalFont, $this->leftParagraph);
                $table->addCell(PhpWord\Shared\Converter::cmToTwip(4.00), $this->vAlignCenterCell)
                    ->addText(
                        $r->getType() === 0 ? $this->anrTranslate('internal') : $this->anrTranslate('external'),
                        $this->normalFont,
                        $this->leftParagraph
                    );
                $table->addCell(PhpWord\Shared\Converter::cmToTwip(8.00), $this->vAlignCenterCell)
                    ->addText(_WT($r->getDescription()), $this->normalFont, $this->leftParagraph);
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
        $recordEntity = $this->recordTable->findById((int)$recordId);
        $internationalTransfers = $recordEntity->getInternationalTransfers();

        //create section
        $tableWord = new PhpWord\PhpWord();
        $section = $tableWord->addSection();

        if (!empty($internationalTransfers)) {
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
                    ->addText(_WT($it->getOrganisation()), $this->normalFont, $this->leftParagraph);
                $table->addCell(PhpWord\Shared\Converter::cmToTwip(4.50), $this->vAlignCenterCell)
                    ->addText(_WT($it->getDescription()), $this->normalFont, $this->leftParagraph);
                $table->addCell(PhpWord\Shared\Converter::cmToTwip(4.50), $this->vAlignCenterCell)
                    ->addText(_WT($it->getCountry()), $this->normalFont, $this->leftParagraph);
                $table->addCell(PhpWord\Shared\Converter::cmToTwip(4.50), $this->vAlignCenterCell)
                    ->addText(_WT($it->getDocuments()), $this->normalFont, $this->leftParagraph);
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
        $recordEntity = $this->recordTable->findById((int)$recordId);
        $processors = $recordEntity->getProcessors();
        $isPdfOutput = $this->currentOutputFormat === self::OUTPUT_FORMAT_PDF;
        $actorLabelWidth = $isPdfOutput ? 4.50 : 10.00;
        $actorNameWidth = $isPdfOutput ? 5.75 : 10.00;
        $actorContactWidth = $isPdfOutput ? 5.75 : 10.00;

        $tableWord = new PhpWord\PhpWord();
        $section = $tableWord->addSection();
        if (empty($processors)) {
            $section->addText($this->anrTranslate('No processor'), $this->normalFont, $this->leftParagraph);
        }

        foreach ($processors as $p) {
            //create section
            $section->addText(_WT($p->getLabel()), $this->boldFont);
            $table = $section->addTable($this->borderTable);

            $table->addRow(400);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(4.00), $this->grayCell)
                ->addText($this->anrTranslate('Name'), $this->boldFont, $this->leftParagraph);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(14.00), $this->vAlignCenterCell)
                ->addText(_WT($p->getLabel()), $this->normalFont, $this->leftParagraph);
            $table->addRow(400);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(4.00), $this->grayCell)
                ->addText($this->anrTranslate('Contact'), $this->boldFont, $this->leftParagraph);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(14.00), $this->vAlignCenterCell)
                ->addText(_WT($p->getContact()), $this->normalFont, $this->leftParagraph);
            $table->addRow(400);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(4.00), $this->grayCell)
                ->addText($this->anrTranslate('Activities'), $this->boldFont, $this->leftParagraph);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(14.00), $this->vAlignCenterCell)
                ->addText(_WT($p->getActivities()), $this->normalFont, $this->leftParagraph);
            $table->addRow(400);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(4.00), $this->grayCell)
                ->addText($this->anrTranslate('Security measures'), $this->boldFont, $this->leftParagraph);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(14.00), $this->vAlignCenterCell)
                ->addText(_WT($p->getSecMeasures()), $this->normalFont, $this->leftParagraph);

            $section->addTextBreak(1);
            $section->addText($this->anrTranslate('Actors'), $this->boldFont);
            $tableActor = $section->addTable(
                $isPdfOutput
                    ? array_merge($this->borderTable, [
                        'layout' => PhpWord\Style\Table::LAYOUT_FIXED,
                        'align' => 'center',
                        'cellMarginLeft' => 55,
                        'cellMarginRight' => 55,
                    ])
                    : $this->borderTable
            );

            $tableActor->addRow(400);
            $tableActor->addCell(PhpWord\Shared\Converter::cmToTwip($actorLabelWidth), $this->grayCell)
                ->addText($this->anrTranslate('Actor'), $this->boldFont, $this->centerParagraph);
            $tableActor->addCell(PhpWord\Shared\Converter::cmToTwip($actorNameWidth), $this->grayCell)
                ->addText($this->anrTranslate('Name'), $this->boldFont, $this->centerParagraph);
            $tableActor->addCell(PhpWord\Shared\Converter::cmToTwip($actorContactWidth), $this->grayCell)
                ->addText($this->anrTranslate('Contact'), $this->boldFont, $this->centerParagraph);

            $tableActor->addRow(400);
            $tableActor->addCell(PhpWord\Shared\Converter::cmToTwip($actorLabelWidth), $this->grayCell)
                ->addText(
                    $this->anrTranslate('Representative'),
                    $this->boldFont,
                    $this->leftParagraph
                );
            $tableActor->addCell(PhpWord\Shared\Converter::cmToTwip($actorNameWidth), $this->vAlignCenterCell)
                ->addText(
                    _WT($p->getRepresentative() ? $p->getRepresentative()->getLabel() : ''),
                    $this->normalFont,
                    $this->leftParagraph
                );
            $tableActor->addCell(PhpWord\Shared\Converter::cmToTwip($actorContactWidth), $this->vAlignCenterCell)
                ->addText(
                    _WT($p->getRepresentative() ? $p->getRepresentative()->getContact() : ''),
                    $this->normalFont,
                    $this->leftParagraph
                );

            $tableActor->addRow(400);
            $tableActor->addCell(PhpWord\Shared\Converter::cmToTwip($actorLabelWidth), $this->grayCell)
                ->addText($this->anrTranslate('Data protection officer'), $this->boldFont, $this->leftParagraph);
            $tableActor->addCell(PhpWord\Shared\Converter::cmToTwip($actorNameWidth), $this->vAlignCenterCell)
                ->addText(
                    _WT($p->getDpo() ? $p->getDpo()->getLabel() : ''),
                    $this->normalFont,
                    $this->leftParagraph
                );
            $tableActor->addCell(PhpWord\Shared\Converter::cmToTwip($actorContactWidth), $this->vAlignCenterCell)
                ->addText(
                    _WT($p->getDpo() ? $p->getDpo()->getContact() : ''),
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
        /** @var Entity\Record[] $recordEntities */
        $recordEntities = $this->recordTable->getEntityByFields(['anr' => $this->anr->getId()]);

        $result = '';

        foreach ($recordEntities as $recordEntity) {
            $tableWord = new PhpWord\PhpWord();
            $section = $tableWord->addSection();
            $tableWord->addTitleStyle(1, $this->titleFont);
            $section->addTitle(_WT($recordEntity->getlabel()));
            $result .= $this->getWordXmlFromWordObject($tableWord);
            $result .= $this->generateTableRecordGDPR($recordEntity->id);
            //create section
            $tableWord = new PhpWord\PhpWord();
            $section = $tableWord->addSection();
            $tableWord->addTitleStyle(2, $this->titleFont);
            $section->addTitle($this->anrTranslate('Actors'), 2);
            $result .= $this->getWordXmlFromWordObject($tableWord);
            $result .= $this->generateTableRecordActors($recordEntity->getId());
            //create section
            $tableWord = new PhpWord\PhpWord();
            $section = $tableWord->addSection();
            $tableWord->addTitleStyle(2, $this->titleFont);
            $section->addTitle($this->anrTranslate('Categories of personal data'), 2);
            $result .= $this->getWordXmlFromWordObject($tableWord);
            $result .= $this->generateTableRecordPersonalData($recordEntity->getId());
            //create section
            $tableWord = new PhpWord\PhpWord();
            $section = $tableWord->addSection();
            $tableWord->addTitleStyle(2, $this->titleFont);
            $section->addTitle($this->anrTranslate('Recipients'), 2);
            $result .= $this->getWordXmlFromWordObject($tableWord);
            $result .= $this->generateTableRecordRecipients($recordEntity->getId());
            //create section
            $tableWord = new PhpWord\PhpWord();
            $section = $tableWord->addSection();
            $tableWord->addTitleStyle(2, $this->titleFont);
            $section->addTitle($this->anrTranslate('International transfers'), 2);
            $result .= $this->getWordXmlFromWordObject($tableWord);
            $result .= $this->generateTableRecordInternationalTransfers($recordEntity->getId());
            //create section
            $tableWord = new PhpWord\PhpWord();
            $section = $tableWord->addSection();
            $tableWord->addTitleStyle(2, $this->titleFont);
            $section->addTitle($this->anrTranslate('Processors'), 2);
            $result .= $this->getWordXmlFromWordObject($tableWord);
            $result .= $this->generateTableRecordProcessors($recordEntity->getId());
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
        $impactScaleTypesByCriterion = [
            'c' => ScaleImpactTypeSuperClass::SCALE_TYPE_C,
            'i' => ScaleImpactTypeSuperClass::SCALE_TYPE_I,
            'd' => ScaleImpactTypeSuperClass::SCALE_TYPE_D,
        ];
        $instanceCriteria = Entity\Instance::getAvailableScalesCriteria();
        $impactLabelWidth = 1.20;
        $impactValueWidth = 1.20;
        $impactDescriptionWidth = 5.60;
        $consequenceLabelWidth = 3.10;
        $consequenceValueWidth = 1.20;
        $consequenceDescriptionWidth = 5.70;
        $impactGroupWidth = $impactLabelWidth + $impactValueWidth + $impactDescriptionWidth;
        $consequenceGroupWidth = $consequenceLabelWidth + $consequenceValueWidth + $consequenceDescriptionWidth;
        $totalWidth = $impactGroupWidth + $consequenceGroupWidth;
        $impactDescriptionsByType = [];
        $impactScale = $this->scalesCacheHelper->getCachedScaleByType($this->anr, ScaleSuperClass::TYPE_IMPACT);

        foreach ($impactScale->getScaleImpactTypes() as $scaleImpactType) {
            if ($scaleImpactType->isHidden()
                || !in_array($scaleImpactType->getType(), $impactScaleTypesByCriterion, true)
            ) {
                continue;
            }

            $impactDescriptionsByType[$scaleImpactType->getType()] = [];
            foreach ($scaleImpactType->getScaleComments() as $scaleComment) {
                $impactDescriptionsByType[$scaleImpactType->getType()][$scaleComment->getScaleIndex()] = $scaleComment
                    ->getComment($this->currentLangAnrIndex);
            }
        }

        $tableWord = new PhpWord\PhpWord();
        $section = $tableWord->addSection();
        $table = $section->addTable(array_merge($this->borderTable, [
            'layout' => PhpWord\Style\Table::LAYOUT_FIXED,
        ]));

        //header
        if (!empty($instances)) {
            $table->addRow(400, $this->tblHeader);
            $table->addCell(
                PhpWord\Shared\Converter::cmToTwip($impactGroupWidth),
                $this->setColSpanCell(3, 'DFDFDF')
            )
                ->addText($this->anrTranslate('Impact'), $this->boldFont, $this->centerParagraph);
            $table->addCell(
                PhpWord\Shared\Converter::cmToTwip($consequenceGroupWidth),
                $this->setColSpanCell(3, 'DFDFDF')
            )
                ->addText($this->anrTranslate('Consequences'), $this->boldFont, $this->centerParagraph);
            $table->addRow(400, $this->tblHeader);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip($impactLabelWidth), $this->grayCell)
                ->addText($this->anrTranslate('CIA'), $this->boldFont, $this->centerParagraph);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip($impactValueWidth), $this->grayCell)
                ->addText($this->anrTranslate('Value'), $this->boldFont, $this->centerParagraph);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip($impactDescriptionWidth), $this->grayCell)
                ->addText($this->anrTranslate('Description'), $this->boldFont, $this->centerParagraph);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip($consequenceLabelWidth), $this->grayCell)
                ->addText($this->anrTranslate('Consequences'), $this->boldFont, $this->centerParagraph);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip($consequenceValueWidth), $this->grayCell)
                ->addText($this->anrTranslate('Value'), $this->boldFont, $this->centerParagraph);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip($consequenceDescriptionWidth), $this->grayCell)
                ->addText($this->anrTranslate('Description'), $this->boldFont, $this->centerParagraph);
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
            foreach ($instanceConsequences as $keyConsequence => $instanceConsequence) {
                if ($instanceConsequence['scaleImpactType'] < 4) {
                    unset($instanceConsequences[$keyConsequence]);
                }
            }
            //reinitialization keys
            $instanceConsequences = array_values($instanceConsequences);
            $headerImpact = false;
            foreach ($impacts as $keyImpact => $impact) {
                $headerConsequence = false;
                foreach ($instanceConsequences as $instanceConsequence) {
                    if ($instanceConsequence[$impact . '_risk'] >= 0) {
                        if (!$headerImpact) {
                            $table->addRow(400);
                            $table->addCell(
                                PhpWord\Shared\Converter::cmToTwip($totalWidth),
                                $this->setColSpanCell(6, 'DBE5F1')
                            )
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
                            $impactDescription = $impactDescriptionsByType[$impactScaleTypesByCriterion[$impact]][
                                $instance->{'get' . $instanceCriteria[$impact]}() !== -1
                                ? $instance->{'get' . $instanceCriteria[$impact]}()
                                : 0
                            ] ?? '';
                            $translatedImpact = ucfirst($impact);
                            if ($impact === 'd') {
                                $translatedImpact = ucfirst($this->anrTranslate('A'));
                            }
                            $table->addCell(
                                PhpWord\Shared\Converter::cmToTwip($impactLabelWidth),
                                $this->restartAndCenterCell
                            )
                                ->addText($translatedImpact, $this->boldFont, $this->centerParagraph);
                            $table->addCell(
                                PhpWord\Shared\Converter::cmToTwip($impactValueWidth),
                                $this->restartAndCenterCell
                            )
                                ->addText(
                                    $this->formatDisplayValue($instance->{'get' . $instanceCriteria[$impact]}()),
                                    $this->boldFont,
                                    $this->centerParagraph
                                );
                            $table->addCell(
                                PhpWord\Shared\Converter::cmToTwip($impactDescriptionWidth),
                                $this->restartAndCenterCell
                            )
                                ->addText(_WT($impactDescription), $this->normalFont, $this->leftParagraph);
                        } else {
                            $table->addCell(PhpWord\Shared\Converter::cmToTwip($impactLabelWidth), $this->continueCell);
                            $table->addCell(PhpWord\Shared\Converter::cmToTwip($impactValueWidth), $this->continueCell);
                            $table->addCell(
                                PhpWord\Shared\Converter::cmToTwip($impactDescriptionWidth),
                                $this->continueCell
                            );
                        }
                        $consequenceDescription = $instanceConsequence['comments'][
                            $instanceConsequence[$impact . '_risk'] !== -1 ? $instanceConsequence[$impact . '_risk'] : 0
                        ] ?? '';
                        $table->addCell(
                            PhpWord\Shared\Converter::cmToTwip($consequenceLabelWidth),
                            $this->vAlignCenterCell
                        )
                            ->addText(
                                _WT($instanceConsequence['scaleImpactTypeDescription' . $this->currentLangAnrIndex]),
                                $this->boldFont,
                                $this->leftParagraph
                            );
                        $table->addCell(
                            PhpWord\Shared\Converter::cmToTwip($consequenceValueWidth),
                            $this->vAlignCenterCell
                        )
                            ->addText(
                                $this->formatDisplayValue($instanceConsequence[$impact . '_risk']),
                                $this->boldFont,
                                $this->centerParagraph
                            );
                        $table->addCell(
                            PhpWord\Shared\Converter::cmToTwip($consequenceDescriptionWidth),
                            $this->vAlignCenterCell
                        )
                            ->addText(_WT($consequenceDescription), $this->normalFont, $this->leftParagraph);
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

    private function formatResidualRiskAcceptance(InstanceRiskSuperClass $instanceRisk): string
    {
        return $this->formatResidualRiskAcceptanceValues(
            $instanceRisk->getResidualRiskDecision(),
            $instanceRisk->getResidualAcceptanceApproverSupervisor()?->getName(),
            $instanceRisk->getResidualRiskDecidedAt()?->format('Y-m-d'),
            $instanceRisk->getResidualAcceptancePerformedByName(),
            $instanceRisk->isResidualAcceptancePerformedOnBehalf(),
            $instanceRisk->getResidualRiskJustification()
        );
    }

    private function formatResidualRiskAcceptanceFromArray(array $instanceRisk): string
    {
        return $this->formatResidualRiskAcceptanceValues(
            $instanceRisk['residualRiskDecision'] ?? null,
            $instanceRisk['residualAcceptanceApproverSupervisor']['name']
                ?? $instanceRisk['residualRiskAcceptance']['approver']['name']
                ?? null,
            $instanceRisk['residualRiskDecidedAt']
                ?? $instanceRisk['residualRiskAcceptance']['date']
                ?? null,
            $instanceRisk['residualAcceptancePerformedByName']
                ?? $instanceRisk['residualRiskAcceptance']['performedByName']
                ?? null,
            (bool)($instanceRisk['residualAcceptancePerformedOnBehalf']
                ?? $instanceRisk['residualRiskAcceptance']['performedOnBehalf']
                ?? false),
            $instanceRisk['residualRiskJustification'] ?? null
        );
    }

    private function formatResidualRiskAcceptanceValues(
        ?string $decision,
        ?string $approver,
        ?string $decidedAt,
        ?string $performedBy,
        bool $performedOnBehalf,
        ?string $justification
    ): string {
        $lines = [];

        if ($decision !== null && $decision !== '') {
            $translatedDecision = match ($decision) {
                'accepted' => $this->anrTranslate('Accepted'),
                'rejected', 'not_accepted' => $this->anrTranslate('Not accepted'),
                default => $decision,
            };
            $lines[] = $this->anrTranslate('Decision') . ': ' . $translatedDecision;
        }
        if ($approver !== null && $approver !== '') {
            $lines[] = $this->anrTranslate('Approver') . ': ' . $approver;
        }
        if ($decidedAt !== null && $decidedAt !== '') {
            $lines[] = $this->anrTranslate('Date') . ': ' . $decidedAt;
        }
        if ($performedBy !== null && $performedBy !== '') {
            $lines[] = $this->anrTranslate('Performed by') . ': ' . $performedBy;
        }
        if ($performedBy !== null && $performedBy !== '') {
            $lines[] = $this->anrTranslate('Performed on behalf') . ': '
                . ($performedOnBehalf ? $this->anrTranslate('Yes') : $this->anrTranslate('No'));
        }
        if ($justification !== null && $justification !== '') {
            $lines[] = $this->anrTranslate('Justification') . ': ' . $justification;
        }

        return empty($lines) ? '-' : implode("\n", $lines);
    }

    /**
     * Generate the owner table data
     * @return mixed|string The WordXml generated data
     */
    private function generateOwnersTable()
    {
        $supervisors = $this->anrSupervisorTable->findByAnrOrdered($this->anr);
        $risksByOwner = [];
        $globalObjectsUuids = [];

        foreach ($supervisors as $supervisor) {
            foreach ($supervisor->getInstanceRisks() as $instanceRisk) {
                $uniqueKey = $instanceRisk->getInstance()->getObject()->getUuid()
                    . $instanceRisk->getThreat()->getUuid()
                    . $instanceRisk->getVulnerability()->getUuid();

                if (in_array($uniqueKey, $globalObjectsUuids, true)) {
                    continue;
                }

                if ($instanceRisk->getInstance()->getObject()->isScopeGlobal()) {
                    $asset = $instanceRisk->getInstance()->getName($this->currentLangAnrIndex) . ' ('
                        . $this->anrTranslate('Global') . ')';
                    $globalObjectsUuids[] = $uniqueKey;
                } else {
                    $asset = $instanceRisk->getInstance()->getHierarchyString();
                }

                $risksByOwner[] = [
                    'owner' => $supervisor->getName(),
                    'asset' => $asset,
                    'threat' => $instanceRisk->getThreat()->getLabel($this->currentLangAnrIndex),
                    'vulnerability' => $instanceRisk->getVulnerability()->getLabel($this->currentLangAnrIndex),
                ];
            }

            foreach ($supervisor->getOperationalInstanceRisks() as $operationalInstanceRisk) {
                $risksByOwner[] = [
                    'owner' => $supervisor->getName(),
                    'asset' => $operationalInstanceRisk->getInstance()->getHierarchyString(),
                    'risk' => $operationalInstanceRisk->getRiskCacheLabel($this->currentLangAnrIndex),
                ];
            }
        }

        $tableWord = new PhpWord\PhpWord();
        $section = $tableWord->addSection();
        $table = $section->addTable($this->borderTable);

        if (!empty($risksByOwner)) {
            $table->addRow(400, $this->tblHeader);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(3.50), $this->restartAndGrayCell)
                ->addText($this->anrTranslate('Owner'), $this->boldFont, $this->centerParagraph);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(6.50), $this->restartAndGrayCell)
                ->addText($this->anrTranslate('Asset'), $this->boldFont, $this->centerParagraph);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(9.00), $this->setColSpanCell(2, 'DFDFDF'))
                ->addText($this->anrTranslate('Risk'), $this->boldFont, $this->centerParagraph);

            $table->addRow(400, $this->tblHeader);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(3.50), $this->continueAndGrayCell);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(6.50), $this->continueAndGrayCell);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(4.50), $this->grayCell)
                ->addText($this->anrTranslate('Threat'), $this->boldFont, $this->centerParagraph);
            $table->addCell(PhpWord\Shared\Converter::cmToTwip(4.50), $this->grayCell)
                ->addText($this->anrTranslate('Vulnerability'), $this->boldFont, $this->centerParagraph);

            $previousOwner = null;
            foreach ($risksByOwner as $risk) {
                $table->addRow(400);
                if ($previousOwner !== $risk['owner']) {
                    $table->addCell(PhpWord\Shared\Converter::cmToTwip(3.50), $this->restartAndCenterCell)
                        ->addText(_WT($risk['owner']), $this->boldFont, $this->leftParagraph);
                } else {
                    $table->addCell(PhpWord\Shared\Converter::cmToTwip(3.50), $this->continueCell);
                }
                $table->addCell(PhpWord\Shared\Converter::cmToTwip(6.50), $this->vAlignCenterCell)
                    ->addText(_WT($risk['asset']), $this->normalFont, $this->leftParagraph);
                if (isset($risk['risk'])) {
                    $table->addCell(PhpWord\Shared\Converter::cmToTwip(9.00), $this->setColSpanCell(2))
                        ->addText(_WT($risk['risk']), $this->normalFont, $this->leftParagraph);
                } else {
                    $table->addCell(PhpWord\Shared\Converter::cmToTwip(4.50), $this->vAlignCenterCell)
                        ->addText(_WT($risk['threat']), $this->normalFont, $this->leftParagraph);
                    $table->addCell(PhpWord\Shared\Converter::cmToTwip(4.50), $this->vAlignCenterCell)
                        ->addText(_WT($risk['vulnerability']), $this->normalFont, $this->leftParagraph);
                }
                $previousOwner = $risk['owner'];
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
            $metadataFieldsHeaders[$anrMetadataField->getId()] = $anrMetadataField->getLabel();
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
            $instanceMetadata = $instance->getInstanceMetadata();
            if (in_array($assetUuid, $assetUuids, true) || $instanceMetadata->isEmpty()) {
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

            foreach ($metadataFieldsHeaders as $fieldId => $fieldLabel) {
                $instanceContext = '';
                foreach ($instanceMetadata as $instanceMetadataRow) {
                    if ($instanceMetadataRow->getAnrInstanceMetadataField()->getId() === $fieldId) {
                        $instanceContext = $instanceMetadataRow->getComment();
                    }
                }
                ${'table' . $typeAsset}->addCell(
                    PhpWord\Shared\Converter::cmToTwip($sizeColumn),
                    $this->vAlignCenterCell
                )->addText(_WT($instanceContext), $this->normalFont, $this->leftParagraph);
            }
        }

        return empty($assetUuids) ? null : $this->getWordXmlFromWordObject($tableWord);
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
                    while (str_contains($group[0], '<li>')) {
                        ++$index;
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

    private function generateWordXmlFromPlainText(
        string $input,
        array $paragraphStyle = [],
        array $firstParagraphStyle = []
    ): string
    {
        $phpWord = new PhpWord\PhpWord();
        $section = $phpWord->addSection();
        $baseParagraphStyle = $this->leftParagraph ?? ['alignment' => 'left', 'spaceAfter' => '1.0'];
        $baseFontStyle = $this->normalFont ?? ['size' => 11];
        $defaultParagraphStyle = array_merge($baseParagraphStyle, $paragraphStyle);
        $firstParagraphStyle = array_merge($defaultParagraphStyle, $firstParagraphStyle);
        $isFirstParagraph = true;

        foreach (preg_split("/\n{2,}/", trim($input)) ?: [] as $paragraph) {
            if ($paragraph === '') {
                continue;
            }

            $section->addText(
                _WT($paragraph),
                $baseFontStyle,
                $isFirstParagraph ? $firstParagraphStyle : $defaultParagraphStyle
            );
            $isFirstParagraph = false;
        }

        return $this->getWordXmlFromWordObject($phpWord);
    }

    private function addLabeledTextRunLine(
        PhpWord\Element\TextRun $textRun,
        string $label,
        string $value,
        array $labelFont,
        array $valueFont,
        int $trailingBreaks = 1
    ): void {
        $textRun->addText($label, $labelFont);
        $this->addMultilineTextRunText($textRun, $value, $valueFont, $trailingBreaks);
    }

    private function addMultilineTextRunText(
        PhpWord\Element\TextRun $textRun,
        string $value,
        array $font,
        int $trailingBreaks = 0
    ): void {
        $lines = preg_split("/\r\n|\r|\n/", trim($value)) ?: [''];
        $lastIndex = count($lines) - 1;

        foreach ($lines as $index => $line) {
            $textRun->addText(htmlspecialchars($line, ENT_COMPAT), $font);
            if ($index < $lastIndex) {
                $textRun->addTextBreak();
            }
        }

        for ($i = 0; $i < $trailingBreaks; ++$i) {
            $textRun->addTextBreak();
        }
    }

    private function convertRichTextToPlainText(string $input): string
    {
        $text = html_entity_decode($input);
        $text = preg_replace('/<br\\s*\\/?>/i', "\n", $text) ?? $text;
        $text = preg_replace('/<\\/p>\\s*<p[^>]*>/i', "\n\n", $text) ?? $text;
        $text = preg_replace('/<li[^>]*>/i', '- ', $text) ?? $text;
        $text = preg_replace('/<\\/li>/i', "\n", $text) ?? $text;
        $text = preg_replace('/<\\/(ul|ol)>/i', "\n", $text) ?? $text;
        $text = strip_tags($text);
        $text = preg_replace("/\r\n|\r/", "\n", $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text);
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
            $singleLevelArray[] = $a;
            if (isset($a['children'])) {
                $children_array = $this->singleLevelArray($a['children']);
                foreach ($children_array as $children) {
                    $singleLevelArray[] = $children;
                }
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
