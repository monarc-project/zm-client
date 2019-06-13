<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Service;

use MonarcCore\Service\DeliveriesModelsService;
use MonarcCore\Service\QuestionChoiceService;
use MonarcCore\Service\QuestionService;
use MonarcFO\Model\Entity\Anr;
use MonarcFO\Model\Entity\MonarcObject;
use MonarcFO\Model\Table\AnrTable;
use MonarcFO\Model\Table\ClientTable;
use MonarcFO\Model\Table\DeliveryTable;
use MonarcFO\Model\Table\InstanceRiskOpTable;
use MonarcFO\Model\Table\InstanceRiskTable;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\TemplateProcessor;
use PhpOffice\PhpWord\Writer\Word2007;

/**
 * This class is the service that handles the generation of the deliverable Word documents throughout the steps of the
 * risk analysis.
 * @package MonarcFO\Service
 */
class DeliverableGenerationService extends \MonarcCore\Service\AbstractService
{
    use \MonarcCore\Model\GetAndSet;

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
    /** @var QuestionService */
    protected $questionService;
    /** @var QuestionChoiceService */
    protected $questionChoiceService;
    /** @var AnrInterviewService */
    protected $interviewService;
    /** @var AnrThreatService */
    protected $threatService;
    /** @var AnrInstanceService */
    protected $instanceService;
    /** @var AnrRecommandationService */
    protected $recommandationService;
    /** @var AnrRecommandationRiskService */
    protected $recommandationRiskService;
    /** @var AnrRecommandationHistoricService */
    protected $recommandationHistoricService;
    /** @var AnrCartoRiskService */
    protected $cartoRiskService;
    /** @var InstanceRiskTable */
    protected $instanceRiskTable;
    /** @var InstanceRiskOpTable */
    protected $instanceRiskOpTable;
    /** @var SoaService */
    protected $soaService;
    /** @var MeasureService */
    protected $measureService;
    /** @var riskOpService */
    protected $riskOpService;
    /** @var riskService */
    protected $riskService;
    /** @var recordService */
    protected $recordService;
    protected $translateService;

    protected $currentLangAnrIndex;

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
     * @see DeliveriesModelsService::getList()
     * @return array An array of delivery models
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
     * @throws \MonarcCore\Exception\Exception If the model or ANR are not found.
     */
    public function generateDeliverableWithValues($anrId, $typeDoc, $values, $data)
    {
        $model = current($this->deliveryModelService->get("table")->getEntityByFields(['id' => $data['template']]));
        if (!$model) {
            throw new \MonarcCore\Exception\Exception("Model `id` not found");
        }

        // Load the ANR
        $anr = $this->anrTable->getEntity($anrId);
        if (!$anr) {
            throw new \MonarcCore\Exception\Exception("Anr `id` not found");
        }

        $delivery = $this->get('entity');

        $data['respCustomer'] = $data['consultants'];
        $data['respSmile'] = $data['managers'];
        $data['name'] = $data['docname'];

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
        switch ($anr->language) {
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
                                                                                            'path2' => ['op'=>'IS NOT', 'value'=>null]]));
            $pathModel .= $model->path2;
            // throw new \MonarcCore\Exception\Exception("Model not found for the language");
        }

        if (!file_exists($pathModel)) {
            throw new \MonarcCore\Exception\Exception("Model not found ".$pathModel);
        }
        $this->currentLangAnrIndex = $anr->language;

        if ($data['typedoc'] == 5) {
          $referential = $data['referential'];
          $record = null;
          $risksByControl = $data['risksByControl'];
        } elseif ($data['typedoc'] == 6) {
          $referential = null;
          $record = $data['record'];
          $risksByControl = false;
        }else {
          $referential = null;
          $record = null;
          $risksByControl = false;
        }

        $values = array_merge_recursive($values, $this->buildValues($anr, $typeDoc, $referential, $record, $risksByControl));
        $values['txt']['TYPE'] = $typeDoc;
        return $this->generateDeliverableWithValuesAndModel($pathModel, $values);
    }

    /**
     * Method called by generateDeliverableWithValues to generate the model from its path and values.
     * @see #generateDeliverableWithValues
     * @param string $modelPath The file path to the DOCX model to use
     * @param array $values The values to fill in the document
     * @return string The path to the generated document
     * @throws \MonarcCore\Exception\Exception If the model is not found
     */
    protected function generateDeliverableWithValuesAndModel($modelPath, $values)
    {
        //verify template exist
        if (!file_exists($modelPath)) {
            throw new \MonarcCore\Exception\Exception("Model path not found: " . $modelPath);
        }

        //create word
        $word = new TemplateProcessor($modelPath);

        if (!empty($values['txt'])) {
            foreach ($values['txt'] as $key => $value) {
                $word->setValue($key, $value);

            }
        }
        if (!empty($values['img']) && method_exists($word, 'setImg')) {
            foreach ($values['img'] as $key => $value) {
                if (isset($value['path']) && file_exists($value['path'])) {
                    $word->setImg($key, $value['path'], $value['options']);
                }
            }
        }
        if (!empty($values['html']) && method_exists($word, 'setHtml')) {
            foreach ($values['html'] as $key => $value) {
                $value = str_replace(
                    ['<br>', '<div>', '</div>', '<!--block-->'],
                    ['<br/>', '', '', ''],
                    $value
                );

                while (strpos($value, '<ul>') !== false) {
                    if (preg_match_all("'<ul>(.*?)</ul>'", $value, $groups)) {
                        foreach ($groups as $group) {
                            $value1 = preg_replace(
                                        ["'<li>'", "'</li>'"],
                                        ['&nbsp;&bull;&nbsp;','<br />'],
                                        $group[0]);

                            $value = preg_replace("'<ul>(.*?)</ul>'", "<br />$value1", $value, 1);

                        }
                    }
                }

                while (strpos($value, '<ol>') !== false) {
                    if (preg_match_all("'<ol>(.*?)</ol>'", $value, $groups)) {
                        foreach ($groups as $group) {
                            $index = 0;
                            while (strpos($group[0], '<li>') !== false) {
                                $index += 1;
                                $group[0] = preg_replace(
                                            ["'<li>'", "'</li>'"],
                                            ["&nbsp;[$index]&nbsp;",'<br />'],
                                            $group[0], 1);
                            }
                            $value = preg_replace("'<ol>(.*?)</ol>'", "<br />$group[0]", $value, 1);
                        }
                    }
                }

                $word->setHtml($key, $value);
            }
        }

        $datapath = './data/';
        $appconfdir = getenv('APP_CONF_DIR') ? getenv('APP_CONF_DIR') : '';
        if( ! empty($appconfdir) ){
            $datapath = $appconfdir.'/data/';
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

        if (!empty($values['img'])) {
            foreach ($values['img'] as $key => $value) {
                if (isset($value['path']) && file_exists($value['path'])) {
                    unlink($value['path']);
                }
            }
        }

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
     * @param Anr $anr The ANR objects
     * @param int $modelCategory The model type
     * @return array The values for the Word document as a key-value array
     */
    protected function buildValues($anr, $modelCategory, $referential = null, $record  = null, $risksByControl = false)
    {
        switch ($modelCategory) {
            case 1:
                return $this->buildContextValidationValues($anr);
            case 2:
                return $this->buildContextModelingValues($anr);
            case 3:
                return $this->buildRiskAssessmentValues($anr);
            case 4:
                return $this->buildImplementationPlanValues($anr);
            case 5:
                return $this->buildStatementOfAppplicabilityValues($anr,$referential,$risksByControl);
            case 6:
                return $this->buildRecordOfProcessingActivitiesValues($anr,$record);
            default:
                return [];
        }
    }

    /**
     * Build values for Step 1 deliverable (context validation)
     * @param Anr $anr The ANR object
     * @return array The key-value array
     */
    protected function buildContextValidationValues($anr)
    {
        // Values read from database
        $values = [
            'txt' => [
                'COMPANY' => $this->getCompanyName(),
            ],
            'html' => [
                'CONTEXT_ANA_RISK' => $anr->contextAnaRisk,
                'CONTEXT_GEST_RISK' => $anr->contextGestRisk,
                'SYNTH_EVAL_THREAT' => $anr->synthThreat,
            ],
        ];

        // Generate impacts table
        $impactsScale = current(current($this->scaleService->getList(1, 0, null, null, ['anr' => $anr->id, 'type' => 1])));
        $impactsTypes = $this->scaleTypeService->getList(1, 0, null, null, ['anr' => $anr->id]);
        $impactsComments = $this->scaleCommentService->getList(1, 0, null, null, ['anr' => $anr->id, 'scale' => $impactsScale['id']]);

        $styleTable = ['borderSize' => 1, 'borderColor' => 'ABABAB', 'align' => 'center', 'cellMarginRight' => '0'];

        $tableWord = new PhpWord();
        $section = $tableWord->addSection();
        $table = $section->addTable($styleTable);

        $styleHeaderCell = ['valign' => 'center', 'bgcolor' => 'DFDFDF', 'size' => 10];
        $styleHeaderFont = ['bold' => true, 'size' => 10];
        $styleContentFontBold = ['bold' => true, 'size' => 10];
        $styleHeaderParagraph =['alignment' => 'center', 'spaceAfter' => '0' ];
        $cellImpactHeader = ['textDirection'=>'btLr','valign' => 'center', 'vMerge' => 'restart'];
        $styleContentCell = ['valign' => 'center', 'align' => 'left', 'size' => 10];
        $styleContentCell2 = ['valign' => 'bottom', 'align' => 'left', 'size' => 10];
        $styleContentCell3 = ['valign' => 'top', 'align' => 'left', 'size' => 10];
        $styleContentFont = ['valign' => 'center', 'bold' => false, 'size' => 10];
        $styleContentParagraph =['Alignment' => 'left', 'spaceAfter' => '0', 'size' => 10 ];
        $styleContentParagraph2 =['Alignment' => 'left', 'spaceAfter' => '0' , 'spaceBefore' => '1'];
        $styleLevelParagraph =['Alignment' => 'center', 'spaceAfter' => '0' ];

        $cellRowSpan = ['vMerge' => 'restart', 'valign' => 'center', 'bgcolor' => 'DFDFDF', 'align' => 'center', 'Alignment' => 'center'];
        $cellRowContinue = ['vMerge' => 'continue','valign' => 'center', 'bgcolor' => 'DFDFDF'];
        $cellColSpan = ['gridSpan' => 3, 'bgcolor' => 'DFDFDF', 'size' => 10, 'valign' => 'center', 'align' => 'center', 'Alignment' => 'center'];

        $table->addRow(400, ['tblHeader' => true]);

        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(2.00), $cellRowSpan)->addText($this->anrTranslate('Level'), $styleHeaderFont, $styleHeaderParagraph);
        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(8.40), $cellColSpan)->addText($this->anrTranslate('Impact'), $styleHeaderFont, $styleHeaderParagraph);
        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(8.60), $cellRowSpan)->addText($this->anrTranslate('Consequences'), $styleHeaderFont, $styleHeaderParagraph);

        // Manually add C/I/D impacts columns
        $table->addRow();
        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(2.00), $cellRowContinue);
        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(2.80), $styleHeaderCell)->addText($this->anrTranslate('Confidentiality'), $styleHeaderFont, $styleHeaderParagraph);
        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(2.80), $styleHeaderCell)->addText($this->anrTranslate('Integrity'), $styleHeaderFont, $styleHeaderParagraph);
        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(2.80), $styleHeaderCell)->addText($this->anrTranslate('Availability'), $styleHeaderFont, $styleHeaderParagraph);
        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(8.60), $cellRowContinue);

        // Fill in each row
        for ($row = $impactsScale['min']; $row <= $impactsScale['max']; ++$row) {
            $cellRowSpan = ['vMerge' => 'restart', 'valign' => 'top', 'bgcolor' => 'FFFFFF'];
            $cellRowContinue = ['vMerge' => 'continue'];

            $table->addRow(400);

            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(2.00), $cellRowSpan)->addText($row, $styleContentFont, ['Alignment' => 'center']);

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
                    if ($comment['scaleImpactType']->id == $impactType['id'] && $comment['val'] == $row) {
                        $commentText = $comment['comment' . $anr->language];
                        break;
                    }
                }

                $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(2.80), $cellRowSpan)->addText(_WT($commentText), $styleContentFont, $styleContentParagraph );
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
                    $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(2.15), $cellRowContinue);
                    $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(2.15), $cellRowContinue);
                    $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(2.15), $cellRowContinue);
                    $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(2.15), $cellRowContinue);
                }

                // Find the appropriate comment
                $commentText = '';
                foreach ($impactsComments as $comment) {
                    if ($comment['scaleImpactType']->id == $impactType['id'] && $comment['val'] == $row) {
                        $commentText = $comment['comment' . $anr->language];
                        break;
                    }
                }


                $cellConsequences = $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(2.80), $styleContentCell);
                $cellConsequencesRun = $cellConsequences ->addTextRun($styleContentCell);
                $cellConsequencesRun -> addText(_WT($this->anrTranslate($impactType['label' . $anr->language])) . ' : ', $styleContentFontBold);
                $cellConsequencesRun -> addText(_WT($commentText), $styleContentParagraph);

            }
        }

        $values['txt']['SCALE_IMPACT'] = $this->getWordXmlFromWordObject($tableWord);
        unset($tableWord);

        // Generate threat scale table
        $threatsScale = current(current($this->scaleService->getList(1, 0, null, null, ['anr' => $anr->id, 'type' => 2])));
        $threatsComments = $this->scaleCommentService->getList(1, 0, null, null, ['anr' => $anr->id, 'scale' => $threatsScale['id']]);

        $tableWord = new PhpWord();
        $section = $tableWord->addSection();
        $table = $section->addTable($styleTable);

        $table->addRow(400, ['tblHeader' => true]);
        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(2.00), $styleHeaderCell)->addText(_WT($this->anrTranslate('Level')), $styleHeaderFont, $styleHeaderParagraph);
        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(17.00), $styleHeaderCell)->addText(_WT($this->anrTranslate('Comment')), $styleHeaderFont, $styleHeaderParagraph);

        // Fill in each row
        for ($row = $threatsScale['min']; $row <= $threatsScale['max']; ++$row) {
            $table->addRow(400);

            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(2.00), $styleContentCell)->addText($row, $styleContentFont, $styleLevelParagraph );

            // Find the appropriate comment
            $commentText = '';
            foreach ($threatsComments as $comment) {
                if ($comment['val'] == $row) {
                    $commentText = $comment['comment' . $anr->language];
                    break;
                }
            }

            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(17.00), $styleContentCell)->addText(_WT($commentText), $styleContentFont, $styleContentParagraph);
        }

        $values['txt']['SCALE_THREAT'] = $this->getWordXmlFromWordObject($tableWord);
        unset($tableWord);

        // Generate vuln table
        $vulnsScale = current(current($this->scaleService->getList(1, 0, null, null, ['anr' => $anr->id, 'type' => 3])));
        $vulnsComments = $this->scaleCommentService->getList(1, 0, null, null, ['anr' => $anr->id, 'scale' => $vulnsScale['id']]);

        $tableWord = new PhpWord();
        $section = $tableWord->addSection();
        $table = $section->addTable($styleTable);

        $table->addRow(400, ['tblHeader' => true]);
        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(2.00), $styleHeaderCell)->addText(_WT($this->anrTranslate('Level')), $styleHeaderFont, $styleHeaderParagraph);
        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(17.00), $styleHeaderCell)->addText(_WT($this->anrTranslate('Comment')), $styleHeaderFont, $styleHeaderParagraph);


        // Fill in each row
        for ($row = $vulnsScale['min']; $row <= $vulnsScale['max']; ++$row) {
            $table->addRow(400);

            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(2.00), $styleContentCell)->addText($row, $styleContentFont, $styleLevelParagraph);

            // Find the appropriate comment
            $commentText = '';
            foreach ($vulnsComments as $comment) {
                if ($comment['val'] == $row) {
                    $commentText = $comment['comment' . $anr->language];
                    break;
                }
            }

            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(17.00), $styleContentCell)->addText(_WT($commentText), $styleContentFont, $styleContentParagraph);
        }

        $values['txt']['SCALE_VULN'] = $this->getWordXmlFromWordObject($tableWord);
        unset($tableWord);

        // Generate information risks table
        $tableWord = new PhpWord();
        $section = $tableWord->addSection();
        $table = $section->addTable(['align' => 'center', 'cellMarginRight' => '0']);

        $risksTableCellStyle        = ['alignment' => 'center', 'valign' => 'center', 'BorderSize' => 20, 'BorderColor' => 'FFFFFF', 'BgColor' => 'FFFFFF'];
        $risksTableGreenCellStyle   = ['alignment' => 'center', 'valign' => 'center', 'BorderSize' => 20, 'BorderColor' => 'FFFFFF', 'BgColor' => 'D6F107'];
        $risksTableOrangeCellStyle  = ['alignment' => 'center', 'valign' => 'center', 'BorderSize' => 20, 'BorderColor' => 'FFFFFF', 'BgColor' => 'FFBC1C'];
        $risksTableRedCellStyle     = ['alignment' => 'center', 'valign' => 'center', 'BorderSize' => 20, 'BorderColor' => 'FFFFFF', 'BgColor' => 'FD661F'];
        $risksTableFontStyleBlack   = ['alignment' => 'center', 'bold' => true, 'color' => '000000'];
        $risksTableFontStyleWhite   = ['alignment' => 'center', 'bold' => true, 'color' => 'FFFFFF'];
        $alignCenter                = ['align' => 'center', 'spaceAfter' => '0'];


        $header = [];
        for ($t = $threatsScale['min']; $t <= $threatsScale['max']; ++$t) {
            for ($v = $vulnsScale['min']; $v <= $vulnsScale['max']; ++$v) {
                $prod = $t * $v;
                if (array_search($prod, $header) === false) {
                    $header[] = $prod;
                    $cellColSpanHeader = ['gridSpan' => (count($header)) , 'size' => 10, 'valign' => 'center', 'align' => 'center', 'Alignment' => 'center'];

                }
            }
        }
        asort($header);

        $size = 13 / (count($header) + 2); // 15cm
        $table->addRow();
        $table->addCell(null, ['gridSpan' => 2])->addText('', $risksTableFontStyleBlack, $alignCenter);
        $table->addCell(null, $cellColSpanHeader)->addText($this->anrTranslate('TxV'), $risksTableFontStyleBlack, $alignCenter);
        $table->addRow();
        $table->addCell(null, $cellImpactHeader)->addText($this->anrTranslate('Impact'), $risksTableFontStyleBlack, $alignCenter);
        $table->addCell(null, $risksTableCellStyle)->addText(' ', $risksTableFontStyleBlack, $alignCenter);
        foreach ($header as $MxV) {
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(1), $risksTableCellStyle)->addText($MxV, $risksTableFontStyleBlack, $alignCenter);
        }

        for ($row = $impactsScale['min']; $row <= $impactsScale['max']; ++$row) {
            $table->addRow(\PhpOffice\Common\Font::centimeterSizeToTwips($size));
            $table->addCell(null, ['vMerge' => 'continue']);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(1), $risksTableCellStyle)->addText($row, $risksTableFontStyleBlack, $alignCenter);

            foreach ($header as $MxV) {
                $value = $MxV * $row;

                if ($value <= $anr->seuil1) {
                    $style = $risksTableGreenCellStyle;
                    $fontStyle = $risksTableFontStyleBlack;
                } else if ($value <= $anr->seuil2) {
                    $style = $risksTableOrangeCellStyle;
                    $fontStyle = $risksTableFontStyleBlack;
                } else {
                    $style = $risksTableRedCellStyle;
                    $fontStyle = $risksTableFontStyleWhite;
                }

                $table->addCell(null, $style)->addText($MxV * $row, $fontStyle, $alignCenter);
            }
        }




        $values['txt']['TABLE_RISKS'] = $this->getWordXmlFromWordObject($tableWord);
        unset($tableWord);

        // Generate operational risks table
        $tableWord = new PhpWord();
        $section = $tableWord->addSection();
        $table = $section->addTable(['align' => 'center', 'cellMarginRight' => '0']);

        $risksTableCellStyle        = ['alignment' => 'center', 'valign' => 'center', 'BorderSize' => 20, 'BorderColor' => 'FFFFFF', 'BgColor' => 'FFFFFF'];
        $risksTableGreenCellStyle   = ['alignment' => 'center', 'valign' => 'center', 'BorderSize' => 20, 'BorderColor' => 'FFFFFF', 'BgColor' => 'D6F107'];
        $risksTableOrangeCellStyle  = ['alignment' => 'center', 'valign' => 'center', 'BorderSize' => 20, 'BorderColor' => 'FFFFFF', 'BgColor' => 'FFBC1C'];
        $risksTableRedCellStyle     = ['alignment' => 'center', 'valign' => 'center', 'BorderSize' => 20, 'BorderColor' => 'FFFFFF', 'BgColor' => 'FD661F'];
        $risksTableFontStyleBlack   = ['alignment' => 'center', 'bold' => true, 'color' => '000000'];
        $risksTableFontStyleWhite   = ['alignment' => 'center', 'bold' => true, 'color' => 'FFFFFF'];
        $alignCenter                = ['align' => 'center', 'spaceAfter' => '0'];


        $header = [];
        for ($t = $threatsScale['min']; $t <= $threatsScale['max']; ++$t) {
                    $header[] = $t;
                    $cellColSpanHeader = ['gridSpan' => (count($header)) , 'size' => 10, 'valign' => 'center', 'align' => 'center', 'Alignment' => 'center'];

        }
        asort($header);


        $size = 0.87;
        $table->addRow();
        $table->addCell(null, ['gridSpan' => 2])->addText('', $risksTableFontStyleBlack, $alignCenter);
        $table->addCell(null, $cellColSpanHeader)->addText($this->anrTranslate('Probability'), $risksTableFontStyleBlack, $alignCenter);
        $table->addRow();
        $table->addCell(null, $cellImpactHeader)->addText($this->anrTranslate('Impact'), $risksTableFontStyleBlack, $alignCenter);
        $table->addCell(null, $risksTableCellStyle)->addText(' ', $risksTableFontStyleBlack, $alignCenter);
        foreach ($header as $Prob) {
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips($size), $risksTableCellStyle)->addText($Prob, $risksTableFontStyleBlack, $alignCenter);
        }

        for ($row = $impactsScale['min']; $row <= $impactsScale['max']; ++$row) {
            $table->addRow(\PhpOffice\Common\Font::centimeterSizeToTwips($size));
            $table->addCell(null, ['vMerge' => 'continue']);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips($size), $risksTableCellStyle)->addText($row, $risksTableFontStyleBlack, $alignCenter);

            foreach ($header as $Prob) {
                $value = $Prob * $row;

                if ($value <= $anr->seuilRolf1) {
                    $style = $risksTableGreenCellStyle;
                    $fontStyle = $risksTableFontStyleBlack;
                } else if ($value <= $anr->seuilRolf2) {
                    $style = $risksTableOrangeCellStyle;
                    $fontStyle = $risksTableFontStyleBlack;
                } else {
                    $style = $risksTableRedCellStyle;
                    $fontStyle = $risksTableFontStyleWhite;
                }

                $table->addCell(null, $style)->addText($Prob * $row, $fontStyle, $alignCenter);
            }
        }

        $values['txt']['TABLE_OP_RISKS'] = $this->getWordXmlFromWordObject($tableWord);
        unset($tableWord);

        // Table which represents "particular attention" threats
        $values['txt']['TABLE_THREATS'] = $this->generateThreatsTable($anr, false);

        // Figure A: Trends (Questions / Answers)
        $questions = $this->questionService->getList(1, 0, null, null, ['anr' => $anr->id]);
        $questionsChoices = $this->questionChoiceService->getList(1, 0, null, null, ['anr' => $anr->id]);

        $tableWord = new PhpWord();
        $section = $tableWord->addSection();
        $table = $section->addTable(['borderSize' => 0, 'borderColor' => 'FFFFFF', 'cellMarginRight' => '0']);


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
                            $responses[] = '- ' . $choice['label' . $anr->language];
                        }
                    }

                    $response = join("\n", $responses);
                } else {
                    foreach ($questionsChoices as $choice) {
                        if ($choice['id'] == $question['response']) {
                            $response = $choice['label' . $anr->language];
                            break;
                        }
                    }
                }
            }

            // no display question, if reply is empty
            if (!empty($response)) {
                $table->addRow(400);
                $table->addCell(11000, $styleContentCell2)->addText(_WT($question['label' . $anr->language]), $styleHeaderFont, $styleContentParagraph2);
                $table->addRow(400);
                $table->addCell(11000, $styleContentCell3)->addText(_WT($response), $styleContentFont, $styleContentParagraph2);
            }
        }

        $values['txt']['TABLE_EVAL_TEND'] = $this->getWordXmlFromWordObject($tableWord);
        unset($tableWord);

        // Figure B: Full threats table
        $values['txt']['TABLE_THREATS_FULL'] = $this->generateThreatsTable($anr, true);

        // Figure C: Interviews table
        $interviews = $this->interviewService->getList(1, 0, null, null, ['anr' => $anr->id]);

        $tableWord = new PhpWord();
        $section = $tableWord->addSection();
        $table = $section->addTable($styleTable);

        if (count($interviews)) {
            $table->addRow(400, ['tblHeader' => true]);

            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(4.00), $styleHeaderCell)->addText($this->anrTranslate("Date"), $styleHeaderFont, $styleHeaderParagraph);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(4.00), $styleHeaderCell)->addText($this->anrTranslate("Department / People"), $styleHeaderFont, $styleHeaderParagraph);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(9.00), $styleHeaderCell)->addText($this->anrTranslate("Contents"), $styleHeaderFont, $styleHeaderParagraph);
        }

        // Fill in each row
        foreach ($interviews as $interview) {
            $table->addRow(400);

            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(4.00), $styleContentCell)->addText(_WT($interview['date']), $styleContentFont, $styleContentParagraph );
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(4.00), $styleContentCell)->addText(_WT($interview['service']), $styleContentFont, $styleContentParagraph );
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(9.00), $styleContentCell)->addText(_WT($interview['content']), $styleContentFont, $styleContentParagraph );
        }

        $values['txt']['TABLE_INTERVIEW'] = $this->getWordXmlFromWordObject($tableWord);
        unset($tableWord);

        return $values;
    }

    /**
     * Build values for Step 2 deliverable (context modeling)
     * @param Anr $anr The ANR object
     * @return array The key-value array
     */
    protected function buildContextModelingValues($anr)
    {
        // Models are incremental, so use values from level-1 model
        $values = $this->buildContextValidationValues($anr);

        $values['html']['SYNTH_ACTIF'] = $anr->synthAct;
        $values['txt']['IMPACTS_APPRECIATION'] = $this->generateImpactsAppreciation($anr);

        return $values;
    }

    /**
     * Build values for Step 3 deliverable (risk assessment)
     * @param Anr $anr The ANR object
     * @return array The key-value array
     */
    protected function buildRiskAssessmentValues($anr)
    {
        // Models are incremental, so use values from level-2 model
        $values = [];
        $values = array_merge($values, $this->buildContextModelingValues($anr));

        $values['html']['DISTRIB_EVAL_RISK'] = $this->getRisksDistribution($anr);

        $values['img']['GRAPH_EVAL_RISK'] = $this->generateRisksGraph($anr);

        $values['txt']['CURRENT_RISK_MAP'] = $this->generateCurrentRiskMap($anr, 'real');
        $values['txt']['TARGET_RISK_MAP'] = $this->generateCurrentRiskMap($anr, 'targeted');

        $values['txt']['RISKS_RECO_FULL'] = $this->generateRisksPlan($anr);
        $values['txt']['OPRISKS_RECO_FULL'] = $this->generateOperationalRisksPlan($anr);

        $values['txt']['TABLE_AUDIT_INSTANCES'] = $this->generateTableAudit($anr);
        $values['txt']['TABLE_AUDIT_RISKS_OP'] = $this->generateTableAuditOp($anr,'ir.cacheNetRisk');


        return $values;
    }

    /**
     * Build values for Step 4 deliverable (Implementation plan)
     * @param Anr $anr The ANR object
     * @return array The key-value array
     */
    protected function buildImplementationPlanValues($anr)
    {
        // Models are incremental, so use values from level-3 model
        $values = [];
        $values = array_merge($values, $this->buildRiskAssessmentValues($anr));
        $values['txt']['TABLE_IMPLEMENTATION_PLAN'] = $this->generateTableImplementationPlan($anr);
        $values['txt']['TABLE_IMPLEMENTATION_HISTORY'] = $this->generateTableImplementationHistory($anr);

        return $values;
    }

    /**
     * Build values for Step 5 deliverable (Statement Of Applicability)
     * @param Anr $anr The ANR object
     * @return array The key-value array
     */
    protected function buildStatementOfAppplicabilityValues($anr, $referential, $risksByControl)
    {
        // Models are incremental, so use values from level-4 model
        $values = [];
        $values = array_merge($values, $this->buildImplementationPlanValues($anr));
        $values['txt']['TABLE_STATEMENT_OF_APPLICABILITY'] = $this->generateTableStatementOfApplicability($anr, $referential);
        if ($risksByControl) {
          $values['txt']['TABLE_RISKS_BY_CONTROL'] = $this->generateTableRisksByControl($anr,$referential);
        }else {
          $values['txt']['TABLE_RISKS_BY_CONTROL'] = null;
        }
        return $values;
    }

    /**
     * Build values for Step 5 deliverable (Record of Processing Activities)
     * @param Anr $anr The ANR object
     * @return array The key-value array
     */
    protected function buildRecordOfProcessingActivitiesValues($anr, $record)
    {
        // Models are incremental, so use values from level-4 model
        $values = [];
        $values = array_merge($values, $this->buildImplementationPlanValues($anr));
        $values['txt']['TABLE_RECORD_INFORMATION'] = $this->generateTableRecordGDPR($anr, $record);
        $values['txt']['TABLE_JOINT_CONTROLLERS'] = $this->generateTableJointControllers($anr, $record);
        $values['txt']['TABLE_PROCESSORS'] = $this->generateTableProcessors($anr, $record);
        return $values;
    }

    /**
     * Generate Current Risk Map
     * @param $anr
     * @param string $type
     * @return string
     */
    protected function generateCurrentRiskMap($anr, $type = 'real'){

        $risksTableCellStyle        = ['alignment' => 'center', 'valign' => 'center', 'BorderSize' => 20, 'BorderColor' => 'FFFFFF', 'BgColor' => 'FFFFFF'];
        $risksTableGreenCellStyle   = ['alignment' => 'center', 'valign' => 'center', 'BorderSize' => 20, 'BorderColor' => 'FFFFFF', 'BgColor' => 'D6F107'];
        $risksTableOrangeCellStyle  = ['alignment' => 'center', 'valign' => 'center', 'BorderSize' => 20, 'BorderColor' => 'FFFFFF', 'BgColor' => 'FFBC1C'];
        $risksTableRedCellStyle     = ['alignment' => 'center', 'valign' => 'center', 'BorderSize' => 20, 'BorderColor' => 'FFFFFF', 'BgColor' => 'FD661F'];
        $risksTableFontStyleBlack   = ['alignment' => 'center', 'bold' => true, 'color' => '000000'];
        $alignCenter                = ['align' => 'center', 'spaceAfter' => '0'];
        $alignLeft                  = ['align' => 'left', 'spaceAfter' => '0'];
        $cellImpactHeader           = ['textDirection'=>'btLr','valign' => 'center', 'vMerge' => 'restart'];


        /** @var AnrCartoRiskService $cartoRiskService */
        $cartoRiskService = $this->get('cartoRiskService');
        $cartoRisk = ($type == 'real') ? $cartoRiskService->getCartoReal($anr->id) : $cartoRiskService->getCartoTargeted($anr->id);

        //header
        if (isset($cartoRisk['MxV'])) {

            // Generate risks table
            $tableWord = new PhpWord();
            $section = $tableWord->addSection();
            $table = $section->addTable(['align' => 'center', 'cellMarginRight' => '0']);

            $header = $cartoRisk['MxV'];
            $size = 13 / (count($header) + 2); // 15cm
            $table->addRow(\PhpOffice\Common\Font::centimeterSizeToTwips($size));
            $table->addCell(null, ['gridSpan' => 2])->addText('', $risksTableFontStyleBlack, $alignCenter);
            $table->addCell(null, ['gridSpan' => (count($header))])->addText($this->anrTranslate('TxV'), $risksTableFontStyleBlack, $alignCenter);

            $table->addRow(\PhpOffice\Common\Font::centimeterSizeToTwips($size));
            $table->addCell(null, $cellImpactHeader)->addText($this->anrTranslate('Impact'), $risksTableFontStyleBlack, $alignCenter);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips($size), $risksTableCellStyle)->addText(' ', $risksTableFontStyleBlack, $alignCenter);
            foreach ($header as $MxV) {
                $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips($size), $risksTableCellStyle)->addText($MxV, $risksTableFontStyleBlack, $alignCenter);
            }

            //row
            $impacts = $cartoRisk['Impact'];
            $nbLow = 0;
            $nbMedium = 0;
            $nbHigh = 0;
            foreach ($impacts as $impact) {
                $table->addRow(\PhpOffice\Common\Font::centimeterSizeToTwips($size));
                $table->addCell(null, ['vMerge' => 'continue']);
                $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips($size), $risksTableCellStyle)->addText($impact, $risksTableFontStyleBlack, $alignCenter);

                foreach ($header as $MxV) {

                    $value = $MxV * $impact;
                    if (isset($cartoRisk['riskInfo']['counters'][$impact]) && isset($cartoRisk['riskInfo']['counters'][$impact][$MxV])) {
                        $result = $cartoRisk['riskInfo']['counters'][$impact][$MxV] ? $cartoRisk['riskInfo']['counters'][$impact][$MxV]: null;
                    } else {
                        $result = null;
                    }

                    if ($value <= $anr->seuil1) {
                        $style = $risksTableGreenCellStyle;
                        if ($result) {
                            $nbLow += $result;
                        }
                        else {
                          $style['BgColor'] = 'f0f7b2';
                        }
                    } else if ($value <= $anr->seuil2) {
                        $style = $risksTableOrangeCellStyle;
                        if ($result) {
                            $nbMedium += $result;
                        }else {
                          $style['BgColor'] = 'fcdd94';
                        }
                    } else {
                        $style = $risksTableRedCellStyle;
                        if ($result) {
                            $nbHigh += $result;
                        }else {
                          $style['BgColor'] = 'fcb28f';
                        }
                    }
                    $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips($size), $style)->addText($result, $risksTableFontStyleBlack, $alignCenter);
                }
            }


            $risksTableGreenCellStyle   = ['alignment' => 'center', 'valign' => 'center', 'BgColor' => 'D6F107', 'BorderTopSize' => 0, 'BorderBottomSize' => 30, 'BorderColor' => 'FFFFFF'];
            $risksTableGreenCellStyle2  = ['alignment' => 'center', 'valign' => 'center', 'BgColor' => 'f0f7b2', 'BorderTopSize' => 0, 'BorderBottomSize' => 30, 'BorderColor' => 'FFFFFF'];
            $risksTableOrangeCellStyle  = ['alignment' => 'center', 'valign' => 'center', 'BgColor' => 'FFBC1C', 'BorderTopSize' => 50, 'BorderBottomSize' => 30, 'BorderColor' => 'FFFFFF'];
            $risksTableOrangeCellStyle2 = ['alignment' => 'center', 'valign' => 'center', 'BgColor' => 'fcdd94', 'BorderTopSize' => 50, 'BorderBottomSize' => 30, 'BorderColor' => 'FFFFFF'];
            $risksTableRedCellStyle     = ['alignment' => 'center', 'valign' => 'center', 'BgColor' => 'FD661F', 'BorderTopSize' => 50, 'BorderBottomSize' => 30, 'BorderColor' => 'FFFFFF'];
            $risksTableRedCellStyle2    = ['alignment' => 'center', 'valign' => 'center', 'BgColor' => 'fcb28f', 'BorderTopSize' => 50, 'BorderBottomSize' => 30, 'BorderColor' => 'FFFFFF'];

            //legend
            $maxSize = 7;
            $total = $nbLow + $nbMedium + $nbHigh;
            $lowSize = ($total) ? ($maxSize * $nbLow) / $total : 0;
            $mediumSize = ($total) ? ($maxSize * $nbMedium) / $total : 0;
            $highSize = ($total) ? ($maxSize * $nbHigh) / $total : 0;

            $section->addTextBreak(1);
            $tableLegend = $section->addTable();

            $tableLegend = $section->addTable();
            $tableLegend->addRow(\PhpOffice\Common\Font::centimeterSizeToTwips(0.1));
            $tableLegend->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(0.5), ['vMerge' => 'continue']);
            $tableLegend->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(5), $risksTableCellStyle)->addText($nbLow . ' ' . $this->anrTranslate('Low risks'), $risksTableFontStyleBlack, $alignLeft);
            $tableLegend->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips($lowSize), $risksTableGreenCellStyle);

            if(($maxSize - $lowSize) != 0)
            $tableLegend->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips($maxSize - $lowSize), $risksTableGreenCellStyle2);

            $tableLegend = $section->addTable();
            $tableLegend->addRow(\PhpOffice\Common\Font::centimeterSizeToTwips(0.1));
            $tableLegend->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(0.5), ['vMerge' => 'continue']);
            $tableLegend->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(5), $risksTableCellStyle)->addText($nbMedium . ' ' . $this->anrTranslate('Medium risks'), $risksTableFontStyleBlack, $alignLeft);
            $tableLegend->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips($mediumSize), $risksTableOrangeCellStyle);

            if(($maxSize - $mediumSize) != 0)
            $tableLegend->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips($maxSize - $mediumSize), $risksTableOrangeCellStyle2);

            $tableLegend = $section->addTable();
            $tableLegend->addRow(\PhpOffice\Common\Font::centimeterSizeToTwips(0.1));
            $tableLegend->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(0.5), ['vMerge' => 'continue']);
            $tableLegend->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(5), $risksTableCellStyle)->addText($nbHigh . ' ' . $this->anrTranslate('High risks'), $risksTableFontStyleBlack, $alignLeft);
            $tableLegend->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips($highSize), $risksTableRedCellStyle);

            if(($maxSize - $highSize) != 0)
            $tableLegend->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips($maxSize - $highSize), $risksTableRedCellStyle2);



            return $this->getWordXmlFromWordObject($tableWord);
        } else {
            return $this->anrTranslate('No target risks specified in your risk analysis yet.');
        }


    }

    /**
     * Generates the risks graph that is included in the model
     * @param Anr $anr The ANR object
     * @return array An array with the path and details of the generated canvas
     */
    protected function generateRisksGraph($anr)
    {
        $this->cartoRiskService->buildListScalesAndHeaders($anr->id);
        list($counters, $distrib) = $this->cartoRiskService->getCountersRisks('raw'); // raw = without target

        if(is_array($distrib) && count($distrib)>0){
            $gridmax = ceil(max($distrib)/10) * 10;

            $canvas = new \Imagick();
            $canvas->newImage(400, 205, "white");
            $canvas->setImageFormat("png");
            $draw = new \ImagickDraw();

            $draw->setFontSize(10);
            $draw->setFontFamily("NewCenturySchlbk-Roman");
            $draw->setStrokeAntialias(true);

            //Axes principaux
            $draw->line(20, 185, 380, 185);
            $draw->line(20, 5, 20, 185);
            //petites poignées
            $draw->line(18, 5, 20, 5);
            $draw->line(18, 50, 20, 50);
            $draw->line(18, 95, 20, 95);
            $draw->line(18, 140, 20, 140);

            //valeurs intermédiaire
            $draw->annotation(2, 10, $gridmax);
            $draw->annotation(2, 53, ceil($gridmax - (1 * ($gridmax / 4) ) ));
            $draw->annotation(2, 98, ceil($gridmax - (2 * ($gridmax / 4) ) ));
            $draw->annotation(2, 143, ceil($gridmax - (3 * ($gridmax / 4) ) ));

            //grille
            $draw->setStrokeColor('#DEDEDE');
            $draw->line(21, 5, 380, 5);
            $draw->line(21, 50, 380, 50);
            $draw->line(21, 95, 380, 95);
            $draw->line(21, 140, 380, 140);

            for($i = 40 ; $i <= 400 ; $i+= 20){
                $draw->line($i, 5, $i, 184);
            }

            if(isset($distrib[2]) && $distrib[2]>0){
                $draw->setFillColor("#FD661F");
                $draw->setStrokeColor("transparent");
                $draw->rectangle(29, 195 - (10 + (($distrib[2] * 180)/$gridmax)) , 137, 184);
            }
            $draw->setFillColor('#000000');
            $draw->annotation ( 45 , 200 , ucfirst($this->anrTranslate('High risks')) );

            if(isset($distrib[1]) && $distrib[1]>0){
                $draw->setFillColor("#FFBC1C");
                $draw->setStrokeColor("transparent");
                $draw->rectangle(146, 195 - (10 + (($distrib[1] * 180)/$gridmax)) , 254, 184);
            }
            $draw->setFillColor('#000000');
            $draw->annotation ( 160 , 200 , ucfirst($this->anrTranslate('Medium risks')) );

            if(isset($distrib[0]) && $distrib[0]>0){
                $draw->setFillColor("#D6F107");
                $draw->setStrokeColor("transparent");
                $draw->rectangle(263, 195 - (10 + (($distrib[0] * 180)/$gridmax)) , 371, 184);
            }
            $draw->setFillColor('#000000');
            $draw->annotation ( 280 , 200 , ucfirst($this->anrTranslate('Low risks')) );

            $canvas->drawImage($draw);
            $datapath = './data/';
            $appconfdir = getenv('APP_CONF_DIR') ? getenv('APP_CONF_DIR') : '';
            if( ! empty($appconfdir) ){
                $datapath = $appconfdir.'/data/';
            }
            $path = $datapath.uniqid("", true)."_riskgraph.png";
            $canvas->writeImage($path);

            $return = [
                'path' => $path,
                'options' => ['width' => 500, 'height' => 250, 'align' => 'center'],
            ];

            unset($canvas);

            return $return;
        }
        else{
            return "";
        }

    }

    /**
     * Generate the audit table data
     * @param Anr $anr The ANR object
     * @return mixed|string The generated WordXml data
     */
    protected function generateTableAudit($anr)
    {
        $query = $this->instanceRiskTable->getRepository()->createQueryBuilder('ir');
        $result = $query->select([
            'i.id', 'i.name' . $anr->language . ' as name', 'IDENTITY(i.root)',
            'i.id', 'i.c as impactC',
            'i.id', 'i.i as impactI',
            'i.id', 'i.d as impactA',
            'm.uuid as mid', 'm.label' . $anr->language . ' as mlabel',
            'm.c as threatC',
            'm.i as threatI',
            'm.a as threatA',
            'ir.threatRate',
            'v.uuid as vid', 'v.label' . $anr->language . ' as vlabel',
            'ir.comment',
            'ir.vulnerabilityRate',
            'ir.riskC',
            'ir.riskI',
            'ir.riskD',
            'ir.kindOfMeasure',
            'ir.cacheTargetedRisk',
            'o.uuid as oid', 'o.scope'
        ])->where('ir.anr = :anrid')
            ->setParameter(':anrid', $anr->id)
            ->innerJoin('ir.instance', 'i')
            ->innerJoin('ir.threat', 'm')
            ->innerJoin('ir.vulnerability', 'v')
            ->innerJoin('i.object', 'o')
            ->orderBy('ir.cacheMaxRisk', 'DESC')
            ->getQuery()->getResult();


        $mem_risks = $globalObject = [];
        $instanceTable = $this->get('instanceService')->get('table');
        foreach ($result as $r) {
            if (!isset($globalObject[$r['oid']][$r['mid']][$r['vid']])) {
                $key = null;
                if ($r['scope'] == \MonarcCore\Model\Entity\ObjectSuperClass::SCOPE_GLOBAL) {
                    $key = "o-" . $r['oid'];
                    if (!isset($mem_risks[$key])) {
                        $mem_risks[$key] = [
                            'ctx' => $r['name'] . " (" . $this->anrTranslate('Global') . ")",
                            'risks' => [],
                        ];
                    }
                    $globalObject[$r['oid']][$r['mid']][$r['vid']] = $r['oid'];
                } else {
                    $key = "i-" . $r['id'];
                    if (!isset($mem_risks[$key])) {
                        $instance = current($instanceTable->getEntityByFields(['anr' => $anr->id, 'id' => $r['id']]));
                        $asc = array_reverse($instanceTable->getAscendance($instance));

                        $path = $anr->get('label' . $this->currentLangAnrIndex);
                        foreach ($asc as $a) {
                            $path .= ' > ' . $a['name' . $this->currentLangAnrIndex];
                        }
                        $mem_risks[$key] = [
                            'ctx' => $path,
                            'risks' => [],
                        ];
                    }
                }

                $mem_risks[$key]['risks'][] = [
                    'impactC' => $r['impactC'],
                    'impactI' => $r['impactI'],
                    'impactA' => $r['impactA'],
                    'm' => $r['mlabel'],
                    'threatC' => $r['threatC'],
                    'threatI' => $r['threatI'],
                    'threatA' => $r['threatA'],
                    'threatRate' => $r['threatRate'],
                    'v' => $r['vlabel'],
                    'comment' => $r['comment'],
                    'vulRate' => $r['vulnerabilityRate'],
                    'riskC' => $r['riskC'],
                    'riskI' => $r['riskI'],
                    'riskA' => $r['riskD'],
                    'kindOfMeasure' => $r['kindOfMeasure'],
                    'targetRisk' => $r['cacheTargetedRisk']

                ];
            }
        }

        if (!empty($mem_risks)) {
            $tableWord = new PhpWord();
            $section = $tableWord->addSection();
            $styleTable = ['borderSize' => 1, 'borderColor' => 'ABABAB', 'cellMarginRight' => '0'];
            $table = $section->addTable($styleTable);
            $styleHeaderCell = ['valign' => 'center', 'bgcolor' => '444444', 'size' => 10];
            $styleHeader2Font = ['color' => 'FFFFFF', 'size' => 10];
            $styleContentCell = ['align' => 'left', 'valign' => 'center', 'size' => 10];
            $styleContentFont = ['bold' => false, 'size' => 10];
            $styleContentFontBold = ['bold' => true, 'size' => 10];
            $cellColSpan = ['gridSpan' => 3, 'valign' => 'center', 'bgcolor' => '444444', 'size' => 10];
            $cellColSpan2 = ['gridSpan' => 2, 'valign' => 'center', 'bgcolor' => '444444', 'size' => 10];
            $cellColSpan13 = ['gridSpan' => 13, 'valign' => 'center', 'bgcolor' => 'DFDFDF', 'size' => 10];
            $cellRowSpan = ['vMerge' => 'restart', 'valign' => 'center', 'bgcolor' => '444444', 'align' => 'center', 'Alignment' => 'center'];
            $cellRowContinue = ['vMerge' => 'continue','valign' => 'center', 'bgcolor' => '444444'];


            $alignCenter = ['align' => 'center', 'spaceAfter' => '0'];
            $alignLeft = ['align' => 'left', 'spaceAfter' => '0'];


            $table->addRow(400, ['tblHeader' => true]);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(2.10), $cellColSpan)->addText($this->anrTranslate('Impact'), $styleHeader2Font, $alignCenter);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(5.70), $cellColSpan2)->addText(_WT($this->anrTranslate('Threat')), $styleHeader2Font, $alignCenter);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(10.70), $cellColSpan)->addText(_WT($this->anrTranslate('Vulnerability')), $styleHeader2Font, $alignCenter);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(2.10), $cellColSpan)->addText($this->anrTranslate('Current risk'), $styleHeader2Font, $alignCenter);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(1.00), $cellRowSpan)->addText($this->anrTranslate('Treatment'), $styleHeader2Font, $alignCenter);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(1.00), $cellRowSpan)->addText($this->anrTranslate('Residual risk'), $styleHeader2Font, $alignCenter);

            $table->addRow(400, ['tblHeader' => true]);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(0.70), $styleHeaderCell)->addText('C', $styleHeader2Font, $alignCenter);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(0.70), $styleHeaderCell)->addText('I', $styleHeader2Font, $alignCenter);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(0.70), $styleHeaderCell)->addText($this->anrTranslate('A'), $styleHeader2Font, $alignCenter);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(5.00), $styleHeaderCell)->addText($this->anrTranslate('Label'), $styleHeader2Font, $alignCenter);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(0.70), $styleHeaderCell)->addText($this->anrTranslate('Prob.'), $styleHeader2Font, $alignCenter);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(5.00), $styleHeaderCell)->addText($this->anrTranslate('Label'), $styleHeader2Font, $alignCenter);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(5.00), $styleHeaderCell)->addText($this->anrTranslate('Existing controls'), $styleHeader2Font, $alignCenter);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(0.70), $styleHeaderCell)->addText($this->anrTranslate('Qualif.'), $styleHeader2Font, $alignCenter);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(0.70), $styleHeaderCell)->addText('C', $styleHeader2Font, $alignCenter);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(0.70), $styleHeaderCell)->addText('I', $styleHeader2Font, $alignCenter);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(0.70), $styleHeaderCell)->addText($this->anrTranslate('A'), $styleHeader2Font, $alignCenter);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(1.00), $cellRowContinue);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(1.00), $cellRowContinue);

            $impacts = ['C', 'I', 'A'];
            foreach ($mem_risks as $data) {
                $table->addRow(400);
                $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(19.00), $cellColSpan13)->addText(_WT($data['ctx']), $styleContentFontBold, $alignLeft);

                if (!empty($data['risks'])) {
                    foreach ($data['risks'] as $r) {
                      foreach ($impacts as $impact) {
                        $risk = $r['risk' . ucfirst($impact)];
                        $bgcolor = 'FFBC1C';
                        if ($r['threat' . ucfirst($impact)] == 0) {
                          $bgcolor = 'E7E6E6';
                          $r['risk' . ucfirst($impact)] = null;
                        } else if ($risk == -1) {
                          $bgcolor = '';
                          $r['risk' . ucfirst($impact)] = "-";
                        } else if ($risk <= $anr->seuil1) {
                          $bgcolor = 'D6F107';
                        } else if ($risk > $anr->seuil2) {
                          $bgcolor = 'FD661F';
                        }
                        ${'styleContentCell' . ucfirst($impact)} = ['valign' => 'center', 'bgcolor' => $bgcolor, 'size' => 10];
                      }

                      $bgcolor = 'FFBC1C';
                      if ($r['targetRisk'] == -1)  {
                          $bgcolor = '';
                          $r['targetRisk'] = "-";
                      } else if ($r['targetRisk'] <= $anr->seuil1) {
                          $bgcolor = 'D6F107';
                      } else if ($r['targetRisk'] > $anr->seuil2) {
                          $bgcolor = 'FD661F';
                      }
                      $styleContentCellTargetRisk = ['valign' => 'center', 'bgcolor' => $bgcolor, 'size' => 10];

                      foreach ($r as $key => $value) {
                        if ($value == -1){
                          $r[$key] = '-';
                        }
                      }

                      switch ($r['kindOfMeasure']) {

                      case 1:
                            $Treatment = "Reduction";
                            break;
                        case 2;
                            $Treatment = "Denied";
                            break;
                        case 3:
                            $Treatment = "Accepted";
                            break;
                          case 4:
                            $Treatment = "Shared";
                            break;
                        default:
                            $Treatment = "Not treated";
                      }

                        $table->addRow(400);
                        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(0.70), $styleContentCell)->addText($r['impactC'], $styleContentFont, $alignCenter);
                        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(0.70), $styleContentCell)->addText($r['impactI'], $styleContentFont, $alignCenter);
                        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(0.70), $styleContentCell)->addText($r['impactA'], $styleContentFont, $alignCenter);
                        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(5.00), $styleContentCell)->addText(_WT($r['m']), $styleContentFont, $alignLeft);
                        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(0.70), $styleContentCell)->addText($r['threatRate'], $styleContentFont, $alignCenter);
                        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(5.00), $styleContentCell)->addText(_WT($r['v']), $styleContentFont, $alignLeft);
                        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(5.00), $styleContentCell)->addText(_WT($r['comment']), $styleContentFont, $alignLeft);
                        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(0.70), $styleContentCell)->addText($r['vulRate'], $styleContentFont, $alignCenter);
                        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(0.70), $styleContentCellC)->addText($r['riskC'], $styleContentFontBold, $alignCenter);
                        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(0.70), $styleContentCellI)->addText($r['riskI'], $styleContentFontBold, $alignCenter);
                        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(0.70), $styleContentCellA)->addText($r['riskA'], $styleContentFontBold, $alignCenter);
                        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(1.00), $styleContentCell)->addText(_WT($this->anrTranslate($Treatment)), $styleContentFont, $alignLeft);
                        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(1.00), $styleContentCellTargetRisk)->addText($r['targetRisk'], $styleContentFontBold, $alignCenter);

                    }
                }
            }

            return $this->getWordXmlFromWordObject($tableWord);
        } else {
            return '';
        }
    }

  /**
     * Generates the audit table data for operational risks
     * @param Anr $anr The ANR object
     * @return mixed|string The generated WordXml data
     */
    public function generateTableAuditOp($anr , $orderBy)
    {
        $query = $this->instanceRiskOpTable->getRepository()->createQueryBuilder('ir');
        $result = $query->select([
            'i.id', 'i.name' . $anr->language . ' as name', 'IDENTITY(i.root)',
            'IDENTITY(i.parent) AS parent', 'i.level', 'i.position',
            'ir.riskCacheLabel' . $anr->language . ' AS label',
            'ir.brutProb AS brutProb',
            'ir.brutR AS brutR',
            'ir.brutO AS brutO',
            'ir.brutL AS brutL',
            'ir.brutF AS brutF',
            'ir.brutP AS brutP',
            'ir.cacheBrutRisk AS cacheBrutRisk',
            'ir.netProb AS netProb',
            'ir.netR AS netR',
            'ir.netO AS netO',
            'ir.netL AS netL',
            'ir.netF AS netF',
            'ir.netP AS netP',
            'ir.cacheNetRisk AS cacheNetRisk',
            'ir.comment AS comment',
            'ir.cacheTargetedRisk AS cacheTargetedRisk',
            'ir.kindOfMeasure AS kindOfMeasure'
        ])->where('ir.anr = :anrid')
            ->setParameter(':anrid', $anr->id)
            ->innerJoin('ir.instance', 'i')
            ->innerJoin('i.asset', 'a')
            ->andWhere('a.type = :type')
            ->setParameter(':type', \MonarcCore\Model\Entity\AssetSuperClass::TYPE_PRIMARY)
            ->orderBy($orderBy, 'DESC')
            ->getQuery()->getResult();
        $lst = [];
        $instanceTable = $this->get('instanceService')->get('table');

        foreach ($result as $r) {
            if (!isset($lst[$r['id']])) {
                $instance = current($instanceTable->getEntityByFields(['anr' => $anr->id, 'id' => $r['id']]));
                $asc = array_reverse($instanceTable->getAscendance($instance));
                $path = $anr->get('label' . $this->currentLangAnrIndex);

                foreach ($asc as $a) {
                    $path .= ' > ' . $a['name' . $this->currentLangAnrIndex];
                }
                $lst[$r['id']] = [
                    'path' => $path,
                    'parent' =>$r['parent'],
                    'position' => $r['position'],
                    'risks' => [],
                ];

                if (!empty($instance->root->id) && !isset($lst[$r['parent']]) && ($r['parent'] != $instance->root->id)) {
                    $path = '';
                    $instance = current($instanceTable->getEntityByFields(['anr' => $anr->id, 'id' => $r['parent']]));
                    $asc = array_reverse($instanceTable->getAscendance($instance));
                    $path = $anr->get('label' . $this->currentLangAnrIndex);
                    foreach ($asc as $a) {
                        $path .= ' > ' . $a['name' . $this->currentLangAnrIndex];
                    }
                    $lst[$r['parent']] = [
                      'path' => $path,
                      'parent' =>$instance->parent->id,
                      'position' => $instance->position,
                      'risks' => [],
                    ];
                  }
            }

            $lst[$r['id']]['risks'][] = [
                'label' => $r['label'],
                'brutProb' => $r['brutProb'],
                'brutR' => $r['brutR'],
                'brutO' => $r['brutO'],
                'brutL' => $r['brutL'],
                'brutF' => $r['brutF'],
                'brutP' => $r['brutP'],
                'brutRisk' => $r['cacheBrutRisk'],
                'netProb' => $r['netProb'],
                'netR' => $r['netR'],
                'netO' => $r['netO'],
                'netL' => $r['netL'],
                'netF' => $r['netF'],
                'netP' => $r['netP'],
                'netRisk' => $r['cacheNetRisk'],
                'comment' => $r['comment'],
                'targetedRisk' => $r['cacheTargetedRisk'],
                'kindOfMeasure' => $r['kindOfMeasure']
            ];
        }
        $tree = [];
        $instancesRoot = $instanceTable->getEntityByFields(['anr' => $anr->id, 'parent' => null]);
        foreach ($instancesRoot as $iRoot) {
          $branchTree = $this->buildTree($lst,$iRoot->id);
          if ($branchTree) {
            $tree[$iRoot->id] = $branchTree;
            $tree[$iRoot->id]['position'] = $iRoot->position;
          }
        }

        $lst=[];
        usort($tree, function($a, $b) {
            return $a['position'] <=> $b['position'];
        });
        foreach ($tree as $branch) {
            unset($branch['position']);
            $flat_array = $this->single_level_array($branch);
            $lst = array_merge($lst,$flat_array);
        }

        if (!empty($lst)) {
            $tableWord = new PhpWord();
            $section = $tableWord->addSection();
            $styleTable = ['borderSize' => 1, 'borderColor' => 'ABABAB', 'cellMarginRight' => '0'];
            $table = $section->addTable($styleTable);
            $styleHeaderCell = ['valign' => 'center', 'bgcolor' => '444444', 'size' => 10];
            $styleHeader2Font = ['color' => 'FFFFFF', 'size' => 10];
            $styleContentCell = ['align' => 'left', 'valign' => 'center', 'size' => 10];
            $styleContentFont = ['bold' => false, 'size' => 10];
            $styleContentFontBold = ['bold' => true, 'size' => 10];
            $cellColSpan5 = ['gridSpan' => 5, 'valign' => 'center', 'bgcolor' => '444444', 'size' => 10];
            $cellColSpan11 = ['gridSpan' => 11, 'valign' => 'center', 'bgcolor' => 'DFDFDF', 'size' => 10];
            $cellColSpan7 = ['gridSpan' => 7, 'valign' => 'center', 'bgcolor' => '444444', 'size' => 10];
            $cellColSpan8 = ['gridSpan' => 8, 'valign' => 'center', 'bgcolor' => '444444', 'size' => 10];
            $cellRowSpan = ['vMerge' => 'restart', 'valign' => 'center', 'bgcolor' => '444444', 'align' => 'center', 'Alignment' => 'center'];
            $cellRowContinue = ['vMerge' => 'continue','valign' => 'center', 'bgcolor' => '444444'];
            $alignCenter = ['align' => 'center', 'spaceAfter' => '0'];
            $alignLeft = ['align' => 'left', 'spaceAfter' => '0'];

            $table->addRow(400, ['tblHeader' => true]);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(10.00), $cellRowSpan)->addText($this->anrTranslate('Risk description'), $styleHeader2Font, $alignCenter);
            if ($anr->showRolfBrut == 1) {
              $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(5.50), $cellColSpan7)->addText(_WT($this->anrTranslate('Inherent risk')), $styleHeader2Font, $alignCenter);
            }
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(15.00), $cellColSpan8)->addText(_WT($this->anrTranslate('Net risk')), $styleHeader2Font, $alignCenter);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(2.00), $cellRowSpan)->addText($this->anrTranslate('Treatment'), $styleHeader2Font, $alignCenter);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(2.00), $cellRowSpan)->addText($this->anrTranslate('Residual risk'), $styleHeader2Font, $alignCenter);


            $table->addRow(400, ['tblHeader' => true]);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(10.00), $cellRowContinue);
            if ($anr->showRolfBrut == 1) {
              $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(1.00), $cellRowSpan)->addText(_WT($this->anrTranslate('Prob.')), $styleHeader2Font, $alignCenter);
              $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(3.50), $cellColSpan5)->addText(_WT($this->anrTranslate('Impact')), $styleHeader2Font, $alignCenter);
              $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(1.00), $cellRowSpan)->addText($this->anrTranslate('Current risk'), $styleHeader2Font, $alignCenter);
            }
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(1.00), $cellRowSpan)->addText(_WT($this->anrTranslate('Prob.')), $styleHeader2Font, $alignCenter);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(3.50), $cellColSpan5)->addText(_WT($this->anrTranslate('Impact')), $styleHeader2Font, $alignCenter);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(1.00), $cellRowSpan)->addText($this->anrTranslate('Current risk'), $styleHeader2Font, $alignCenter);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(8.00), $cellRowSpan)->addText($this->anrTranslate('Existing controls'), $styleHeader2Font, $alignCenter);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(2.00), $cellRowContinue);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(2.00), $cellRowContinue);

            $table->addRow(400, ['tblHeader' => true]);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(10.00), $cellRowContinue);
            if ($anr->showRolfBrut == 1) {
              $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(1.00), $cellRowContinue);
              $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(0.70), $cellRowSpan)->addText('R', $styleHeader2Font, $alignCenter);
              $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(0.70), $cellRowSpan)->addText('O', $styleHeader2Font, $alignCenter);
              $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(0.70), $cellRowSpan)->addText('L', $styleHeader2Font, $alignCenter);
              $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(0.70), $cellRowSpan)->addText('F', $styleHeader2Font, $alignCenter);
              $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(0.70), $cellRowSpan)->addText('P', $styleHeader2Font, $alignCenter);
              $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(1.00), $cellRowContinue);
            }
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(1.00), $cellRowContinue);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(0.70), $cellRowSpan)->addText('R', $styleHeader2Font, $alignCenter);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(0.70), $cellRowSpan)->addText('O', $styleHeader2Font, $alignCenter);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(0.70), $cellRowSpan)->addText('L', $styleHeader2Font, $alignCenter);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(0.70), $cellRowSpan)->addText('F', $styleHeader2Font, $alignCenter);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(0.70), $cellRowSpan)->addText('P', $styleHeader2Font, $alignCenter);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(1.00), $cellRowContinue);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(8.00), $cellRowContinue);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(2.00), $cellRowContinue);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(2.00), $cellRowContinue);


            $risks = ['brutRisk', 'netRisk', 'targetedRisk'];
            foreach ($lst as $data) {
                $table->addRow(400);
                if ($anr->showRolfBrut == 1) {
                  $cellColSpan11 = ['gridSpan' => 18, 'valign' => 'center', 'bgcolor' => 'DFDFDF', 'size' => 10];
                }
                $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(19.00), $cellColSpan11)->addText(_WT($data['path']), $styleContentFontBold, $alignLeft);
                foreach ($data['risks'] as $r) {
                  if (!empty($data['risks'])) {
                    foreach ($risks as $risk) {
                      $bgcolor = 'FFBC1C';
                      if ($r[$risk] == -1) {
                        $bgcolor = '';
                        $r[$risk] = "-";
                      } else if ($r[$risk] <= $anr->seuilRolf1) {
                        $bgcolor = 'D6F107';
                      } else if ($r[$risk] > $anr->seuilRolf2) {
                        $bgcolor = 'FD661F';
                      }
                      ${'styleContentCell' . $risk} = ['valign' => 'center', 'bgcolor' => $bgcolor, 'size' => 10];
                    }

                    switch ($r['kindOfMeasure']) {

                    case 1:
                          $Treatment = "Reduction";
                          break;
                      case 2;
                          $Treatment = "Denied";
                          break;
                      case 3:
                          $Treatment = "Accepted";
                          break;
                        case 4:
                          $Treatment = "Shared";
                          break;
                      default:
                          $Treatment = "Not treated";
                    }

                    foreach ($r as $key => $value) {
                      if ($value == -1){
                          $r[$key] = '-';
                      }
                    }
                        $table->addRow(400);
                        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(10.00), $styleContentCell)->addText(_WT($r['label']), $styleContentFont, $alignLeft);
                        if ($anr->showRolfBrut == 1) {
                          $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(1.00), $styleContentCell)->addText($r['brutProb'], $styleContentFont, $alignCenter);
                          $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(0.70), $styleContentCell)->addText($r['brutR'], $styleContentFont, $alignCenter);
                          $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(0.70), $styleContentCell)->addText($r['brutO'], $styleContentFont, $alignCenter);
                          $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(0.70), $styleContentCell)->addText($r['brutL'], $styleContentFont, $alignCenter);
                          $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(0.70), $styleContentCell)->addText($r['brutF'], $styleContentFont, $alignCenter);
                          $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(0.70), $styleContentCell)->addText($r['brutP'], $styleContentFont, $alignCenter);
                          $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(1.00), $styleContentCellbrutRisk)->addText($r['brutRisk'], $styleContentFontBold, $alignCenter);
                        }
                        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(1.00), $styleContentCell)->addText($r['netProb'], $styleContentFont, $alignCenter);
                        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(0.70), $styleContentCell)->addText($r['netR'], $styleContentFont, $alignCenter);
                        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(0.70), $styleContentCell)->addText($r['netO'], $styleContentFont, $alignCenter);
                        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(0.70), $styleContentCell)->addText($r['netL'], $styleContentFont, $alignCenter);
                        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(0.70), $styleContentCell)->addText($r['netF'], $styleContentFont, $alignCenter);
                        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(0.70), $styleContentCell)->addText($r['netP'], $styleContentFont, $alignCenter);
                        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(1.00), $styleContentCellnetRisk)->addText($r['netRisk'], $styleContentFontBold, $alignCenter);
                        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(8.00), $styleContentCell)->addText(_WT($r['comment']), $styleContentFont, $alignLeft);
                        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(2.00), $styleContentCell)->addText(_WT($this->anrTranslate($Treatment)), $styleContentFont, $alignLeft);
                        if ($r['targetedRisk'] == '-') {
                          $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(2.00), $styleContentCellnetRisk)->addText($r['netRisk'], $styleContentFontBold, $alignCenter);
                        } else {
                          $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(2.00), $styleContentCelltargetedRisk)->addText($r['targetedRisk'], $styleContentFontBold, $alignCenter);
                        }
                    }
                }
            }

            return $this->getWordXmlFromWordObject($tableWord);
        } else {
            return '';
        }
    }

    /**
     * Generates Word-compliant HTML for the risks distribution paragraph
     * @param Anr $anr The ANR object
     * @return string HTML data that can be converted into WordXml data
     */
    protected function getRisksDistribution($anr)
    {
        $this->cartoRiskService->buildListScalesAndHeaders($anr->id);
        list($counters, $distrib) = $this->cartoRiskService->getCountersRisks('raw'); // raw = without target
        $colors = array(0, 1, 2);
        $sum = 0;

        foreach ($colors as $c) {
            if (!isset($distrib[$c])) {
                $distrib[$c] = 0;
            }
            $sum += $distrib[$c];
        }

        $intro = sprintf($this->anrTranslate("The list of risks addressed is provided as an attachment. It lists %d risk(s) of which:"), $sum);
        return $intro .
            '<br/>&nbsp;&nbsp;- ' . sprintf($this->anrTranslate('%d critical risk(s) to be treated as priority'), $distrib[2])  .
            '<br/>&nbsp;&nbsp;- ' . sprintf($this->anrTranslate('%d medium risk(s) to be partially treated'), $distrib[1])  .
            '<br/>&nbsp;&nbsp;- ' . sprintf($this->anrTranslate('%d low risk(s) negligible'), $distrib[0]);
    }

     /**
     * Generates the Risks Plan data
     * @param Anr $anr The ANR object
     * @param bool $full Whether or not the full plan is requested or just an extract
     * @return mixed|string The WordXml data generated
     */
    protected function generateRisksPlan($anr)
    {
        /** @var AnrRecommandationRiskService $recommandationService */
        $recommandationRiskService = $this->recommandationRiskService;
        $recosRisksNotOrdered = $recommandationRiskService->getDeliveryRecommandationsRisks($anr->id);

        //oder by recommandation position asc and importance desc
        $recosRisks = [];
        foreach($recosRisksNotOrdered as $key => $recoRisk) {
            $newKey = $recoRisk->recommandation->position . '-' . -$recoRisk->recommandation->importance . '-' . $recoRisk->recommandation->id . '-' . $key;
            $recosRisks[$newKey] = $recoRisk;
        }
        ksort($recosRisks,SORT_NUMERIC);

        //css
        $styleHeaderCell = ['valign' => 'center', 'bgcolor' => 'DFDFDF', 'size' => 10];
        $styleHeaderFont = ['bold' => true, 'size' => 10];
        $styleContentCell = ['align' => 'left', 'valign' => 'center', 'size' => 10];
        $styleContentRecoFont = ['bold' => true, 'size' => 12];
        $alignCenter = ['Alignment' => 'center', 'spaceAfter' => '0'];
        $alignLeft = ['Alignment' => 'left', 'spaceAfter' => '0'];
        $alignRight = ['Alignment' => 'right', 'spaceAfter' => '0'];
        $styleContentFontRed = ['bold' => true, 'color' => 'FF0000', 'size' => 12];
        $styleContentFont = ['bold' => false, 'size' => 10];
        $styleContentFontBold = ['bold' => true, 'size' => 10];
        $cell = ['gridSpan' => 9, 'bgcolor' => 'DBE5F1', 'valign' => 'center'];
        $cellRowSpan = ['vMerge' => 'restart', 'valign' => 'center', 'bgcolor' => 'DFDFDF', 'align' => 'center', 'Alignment' => 'center'];
        $cellRowContinue = ['vMerge' => 'continue','valign' => 'center', 'bgcolor' => 'DFDFDF'];
        $cellColSpan = ['gridSpan' => 3, 'bgcolor' => 'DFDFDF', 'size' => 10, 'valign' => 'center', 'align' => 'center', 'Alignment' => 'center'];



        //create section
        $tableWord = new PhpWord();
        $section = $tableWord->addSection();
        $table = $section->addTable(['borderSize' => 1, 'borderColor' => 'ABABAB', 'cellMarginRight' => '0']);

        //header if array is not empty
        if (count($recosRisks)) {
            $table->addRow(400, ['tblHeader' => true]);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(3.50), $cellRowSpan)->addText($this->anrTranslate('Asset'), $styleHeaderFont, $alignCenter);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(6.00), $cellRowSpan)->addText($this->anrTranslate('Threat'), $styleHeaderFont, $alignCenter);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(6.00), $cellRowSpan)->addText($this->anrTranslate('Vulnerability'), $styleHeaderFont, $alignCenter);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(6.00), $cellRowSpan)->addText($this->anrTranslate('Existing controls'), $styleHeaderFont, $alignCenter);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(2.10), $cellColSpan)->addText($this->anrTranslate('Current risk'), $styleHeaderFont, $alignCenter);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(2.10), $cellRowSpan)->addText($this->anrTranslate('Treatment'), $styleHeaderFont, $alignCenter);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(2.10), $cellRowSpan)->addText($this->anrTranslate('Residual risk'), $styleHeaderFont, $alignCenter);

            $table->addRow();
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(3.00), $cellRowContinue);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(6.00), $cellRowContinue);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(6.00), $cellRowContinue);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(6.00), $cellRowContinue);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(0.70), $styleHeaderCell)->addText('C', $styleHeaderFont, $alignCenter);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(0.70), $styleHeaderCell)->addText('I', $styleHeaderFont, $alignCenter);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(0.70), $styleHeaderCell)->addText($this->anrTranslate('A'), $styleHeaderFont, $alignCenter);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(2.10), $cellRowContinue);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(2.10), $cellRowContinue);



        }

        $previousRecoId = null;
        $impacts = ['c', 'i', 'd'];

        //unset
        $global = [];
        $toUnset = [];
        foreach ($recosRisks as $recoRisk) {
            if ($recoRisk->instance->object->scope == MonarcObject::SCOPE_GLOBAL) {
                $key = $recoRisk->recommandation->id . ' - ' . $recoRisk->threat->id . ' - ' . $recoRisk->vulnerability->id . ' - ' . $recoRisk->objectGlobal->id;
                if (array_key_exists($key, $global)) {
                    if (array_key_exists($key, $toUnset) && ($recoRisk->instanceRisk->cacheMaxRisk > $toUnset[$key])) {
                        $toUnset[$key] = $recoRisk->instanceRisk->cacheMaxRisk;
                    } else {
                        $toUnset[$key] = max($recoRisk->instanceRisk->cacheMaxRisk, $global[$key]);
                    }
                }
                $global[$key] = $recoRisk->instanceRisk->cacheMaxRisk;
            }
        }

        $alreadySet = [];
        foreach ($recosRisks as $recoRisk) {
            if ($recoRisk->instanceRisk) {
                foreach ($impacts as $impact) {
                    $risk = 'risk' . ucfirst($impact);
                    if ($impact == 'd') {
                      $impact = 'a'; // Changed to get threat->a value;
                    }
                    $bgcolor = 'FFBC1C';
                    if (($recoRisk->instanceRisk->$risk == -1) || (!$recoRisk->threat->$impact)) {
                        $bgcolor = 'E7E6E6';
                        $recoRisk->instanceRisk->$risk = null;
                    } else if ($recoRisk->instanceRisk->$risk <= $anr->seuil1) {
                        $bgcolor = 'D6F107';
                    } else if ($recoRisk->instanceRisk->$risk > $anr->seuil2) {
                        $bgcolor = 'FD661F';
                    }
                    ${'styleContentCell' . ucfirst($impact)} = ['valign' => 'center', 'bgcolor' => $bgcolor, 'size' => 10];



                    $bgcolor = 'FFBC1C';
                    if ($recoRisk->instanceRisk->cacheTargetedRisk == -1)  {
                        $bgcolor = 'E7E6E6';
                    } else if ($recoRisk->instanceRisk->cacheTargetedRisk <= $anr->seuil1) {
                        $bgcolor = 'D6F107';
                    } else if ($recoRisk->instanceRisk->cacheTargetedRisk > $anr->seuil2) {
                        $bgcolor = 'FD661F';
                    }
                    $styleContentCellTargetRisk = ['valign' => 'center', 'bgcolor' => $bgcolor, 'size' => 10];
                }
                $importance = '';
                for ($i = 0; $i <= ($recoRisk->recommandation->importance - 1); $i++) {
                    $importance .= '●';
                }

                if ($recoRisk->recommandation->id != $previousRecoId) {

                    $table->addRow(400);
                    $cellReco = $table -> addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(5.00), $cell);
                    $cellRecoRun = $cellReco->addTextRun($alignCenter);
                    $cellRecoRun -> addText($importance . ' ' ,$styleContentFontRed);
                    $cellRecoRun -> addText(_WT($recoRisk->recommandation->code), $styleContentRecoFont);
                    $cellRecoRun -> addText(' - ' . _WT($recoRisk->recommandation->description), $styleContentRecoFont);
                }

                $continue = true;

                $key = $recoRisk->recommandation->id . ' - ' . $recoRisk->threat->id . ' - ' . $recoRisk->vulnerability->id . ' - ' . (!empty($recoRisk->objectGlobal) ? $recoRisk->objectGlobal->id : null);
                if (isset($toUnset[$key])) {
                    if (($recoRisk->instanceRisk->cacheMaxRisk < $toUnset[$key]) || (isset($alreadySet[$key]))) {
                        $continue = false;
                    } else {
                        $alreadySet[$key] = true;
                    }
                }

                $KindOfTreatment = $recoRisk->instanceRisk->kindOfMeasure;

						switch ($KindOfTreatment) {

   					case 1:
        					$Treatment = "Reduction";
        					break;
    					case 2;
        					$Treatment = "Denied";
        					break;
    					case 3:
        					$Treatment = "Accepted";
        					break;
        				case 4:
        					$Treatment = "Shared";
        					break;
    					default:
        					$Treatment = "Not treated";
						}

                if ($continue) {
                    $table->addRow(400);
                    $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(3.00), $styleContentCell)->addText(_WT($recoRisk->instance->{'name' . $anr->language}), $styleContentFont, $alignLeft);
                    $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(6.00), $styleContentCell)->addText(_WT($recoRisk->threat->{'label' . $anr->language}), $styleContentFont, $alignLeft);
                    $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(6.00), $styleContentCell)->addText(_WT($recoRisk->vulnerability->{'label' . $anr->language}), $styleContentFont, $alignLeft);
                    $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(6.00), $styleContentCell)->addText(_WT($recoRisk->instanceRisk->comment), $styleContentFont, $alignLeft);
                    $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(0.70), $styleContentCellC)->addText($recoRisk->instanceRisk->riskC, $styleContentFontBold, $alignCenter);
                    $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(0.70), $styleContentCellI)->addText($recoRisk->instanceRisk->riskI, $styleContentFontBold, $alignCenter);
                    $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(0.70), $styleContentCellA)->addText($recoRisk->instanceRisk->riskD, $styleContentFontBold, $alignCenter);
						        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(2.10), $styleContentCell)->addText(_WT($this->anrTranslate($Treatment)), $styleContentFont, $alignLeft);
                    $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(2.10), $styleContentCellTargetRisk)->addText($recoRisk->instanceRisk->cacheTargetedRisk, $styleHeaderFont, $alignCenter);
                }
            }
            $previousRecoId = $recoRisk->recommandation->id;
        }

        return $this->getWordXmlFromWordObject($tableWord);
    }

     /**
     * Generates the Operational Risks Plan data
     * @param Anr $anr The ANR object
     * @param bool $full Whether or not the full plan is requested or just an extract
     * @return mixed|string The WordXml data generated
     */
    protected function generateOperationalRisksPlan($anr)
    {
        /** @var AnrRecommandationRiskService $recommandationService */
        $recommandationRiskService = $this->recommandationRiskService;
        $recosRisksNotOrdered = $recommandationRiskService->getDeliveryRecommandationsRisks($anr->id);

        //oder by recommandation position asc and importance desc
        $recosRisks = [];
        foreach($recosRisksNotOrdered as $key => $recoRisk) {
            $newKey = $recoRisk->recommandation->position . '-' . -$recoRisk->recommandation->importance . '-' . $recoRisk->recommandation->id . '-' . $key;
            $recosRisks[$newKey] = $recoRisk;
        }
        ksort($recosRisks,SORT_NUMERIC);

        //css
        $styleHeaderCell = ['valign' => 'center', 'bgcolor' => 'DFDFDF', 'size' => 10];
        $styleHeaderFont = ['bold' => true, 'size' => 10];
        $styleContentCell = ['align' => 'left', 'valign' => 'center', 'size' => 10];
        $styleContentRecoFont = ['bold' => true, 'size' => 12];
        $styleContentFont = ['bold' => false, 'size' => 10];
        $styleContentFontBold = ['bold' => true, 'size' => 10];
        $alignCenter = ['Alignment' => 'center', 'spaceAfter' => '0'];
        $alignLeft = ['Alignment' => 'left', 'spaceAfter' => '0'];
        $alignRight = ['Alignment' => 'right', 'spaceAfter' => '0'];
        $styleContentFontRed = ['bold' => true, 'color' => 'FF0000', 'size' => 12];
        $cell = ['gridSpan' => 6, 'bgcolor' => 'DBE5F1', 'size' => 10, 'valign' => 'center'];

        //create section
        $tableWord = new PhpWord();
        $section = $tableWord->addSection();
        $table = $section->addTable(['borderSize' => 1, 'borderColor' => 'ABABAB', 'cellMarginRight' => '0']);

        //header if array is not empty
        if (count($recosRisks)) {
            $table->addRow(400, ['tblHeader' => true]);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(3.00), $styleHeaderCell)->addText($this->anrTranslate('Asset'), $styleHeaderFont, $alignCenter);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(12.20), $styleHeaderCell)->addText($this->anrTranslate('Risk description'), $styleHeaderFont, $alignCenter);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(6.00), $styleHeaderCell)->addText($this->anrTranslate('Existing controls'), $styleHeaderFont, $alignCenter);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(2.10), $styleHeaderCell)->addText($this->anrTranslate('Current risk'), $styleHeaderFont, $alignCenter);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(2.10), $styleHeaderCell)->addText($this->anrTranslate('Treatment'), $styleHeaderFont, $alignCenter);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(2.10), $styleHeaderCell)->addText($this->anrTranslate('Residual risk'), $styleHeaderFont, $alignCenter);
        }

        $previousRecoId = null;

        //$alreadySet = [];
        foreach ($recosRisks as $recoRisk) {
            if ($recoRisk->instanceRiskOp) {
                    $bgcolor = 'FFBC1C';
                    if ($recoRisk->instanceRiskOp->cacheNetRisk == -1)  {
                        $bgcolor = 'E7E6E6';
                    } else if ($recoRisk->instanceRiskOp->cacheNetRisk <= $anr->seuilRolf1) {
                        $bgcolor = 'D6F107';
                    } else if ($recoRisk->instanceRiskOp->cacheNetRisk > $anr->seuilRolf2) {
                        $bgcolor = 'FD661F';
                    }
                    $styleContentCellNetRisk = ['valign' => 'center', 'bgcolor' => $bgcolor, 'size' => 10];


                    $bgcolor = 'FFBC1C';
                    if ($recoRisk->instanceRiskOp->cacheTargetedRisk == -1)  {
                        $bgcolor = 'E7E6E6';
                    } else if ($recoRisk->instanceRiskOp->cacheTargetedRisk <= $anr->seuilRolf1) {
                        $bgcolor = 'D6F107';
                    } else if ($recoRisk->instanceRiskOp->cacheTargetedRisk > $anr->seuilRolf2) {
                        $bgcolor = 'FD661F';
                    }
                    $styleContentCellTargetRisk = ['valign' => 'center', 'bgcolor' => $bgcolor, 'size' => 10];


                $importance = '';
                for ($i = 0; $i <= ($recoRisk->recommandation->importance - 1); $i++) {
                    $importance .= '●';
                }

                if ($recoRisk->recommandation->id != $previousRecoId) {

                    $table->addRow(400);
                    $cellReco = $table -> addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(5.00), $cell);
                    $cellRecoRun = $cellReco->addTextRun($alignCenter);
                    $cellRecoRun -> addText($importance . ' ' ,$styleContentFontRed);
                    $cellRecoRun -> addText(_WT($recoRisk->recommandation->code), $styleContentRecoFont);
                    $cellRecoRun -> addText(' - ' . _WT($recoRisk->recommandation->description), $styleContentRecoFont);
                }

                $KindOfTreatment = $recoRisk->instanceRiskOp->kindOfMeasure;

						switch ($KindOfTreatment) {

   					case 1:
        					$Treatment = "Reduction";
        					break;
    					case 2;
        					$Treatment = "Denied";
        					break;
    					case 3:
        					$Treatment = "Accepted";
        					break;
        				case 4:
        					$Treatment = "Shared";
        					break;
    					default:
        					$Treatment = "Not treated";
						}

                    $table->addRow(400);
                    $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(3.00), $styleContentCell)->addText(_WT($recoRisk->instance->{'name' . $anr->language}), $styleContentFont, $alignLeft);
						        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(12.20), $styleContentCell)->addText(_WT($recoRisk->instanceRiskOp->{'riskCacheLabel' . $anr->language}), $styleContentFont, $alignLeft);
                    $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(6.00), $styleContentCell)->addText(_WT($recoRisk->instanceRiskOp->comment), $styleContentFont, $alignLeft);
                    $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(2.10), $styleContentCellNetRisk)->addText($recoRisk->instanceRiskOp->cacheNetRisk, $styleContentFontBold, $alignCenter);
                    $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(2.10), $styleContentCell)->addText(_WT($this->anrTranslate($Treatment)), $styleContentFont, $alignLeft);
                    if ($recoRisk->instanceRiskOp->cacheTargetedRisk == '-') {
                      $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(2.10), $styleContentCellTargetRisk)->addText($recoRisk->instanceRiskOp->cacheNetRisk, $styleHeaderFont, $alignCenter);
                    } else {
                      $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(2.10), $styleContentCellTargetRisk)->addText($recoRisk->instanceRiskOp->cacheTargetedRisk, $styleHeaderFont, $alignCenter);
                    }

         	          $previousRecoId = $recoRisk->recommandation->id;
            }

        }

        return $this->getWordXmlFromWordObject($tableWord);
    }

      /**
      * Generates the Implamentation Recommendations Plan data
      * @param Anr $anr The ANR object
      * @param bool $full Whether or not the full plan is requested or just an extract
      * @return mixed|string The WordXml data generated
      */
    protected function generateTableImplementationPlan($anr)
    {
       /** @var AnrRecommandationRiskService $recommandationService */
       $recommandationRiskService = $this->recommandationRiskService;
       $recosRisksNotOrdered = $recommandationRiskService->getDeliveryRecommandationsRisks($anr->id);

       //keep the recommandation's order from the application
       $recoLists = [];
       foreach($recosRisksNotOrdered as $key => $recoRisk) {
          if (!in_array($recoRisk->recommandation,$recoLists))
            $recoLists[$recoRisk->recommandation->position] = $recoRisk->recommandation;
       }
       ksort($recoLists,SORT_NUMERIC);

       //css
       $styleHeaderCell = ['valign' => 'center', 'bgcolor' => 'DFDFDF', 'size' => 10];
       $styleHeaderFont = ['bold' => true, 'size' => 10];
       $styleContentCell = ['align' => 'left', 'valign' => 'center', 'size' => 10];
       $styleContentFont = ['bold' => false, 'size' => 10];
       $styleContentFontBold = ['bold' => true, 'size' => 10];
       $alignCenter = ['Alignment' => 'center', 'spaceAfter' => '0'];
       $alignLeft = ['Alignment' => 'left', 'spaceAfter' => '0'];
       $styleContentFontRed = ['bold' => true, 'color' => 'FF0000', 'size' => 12];

       //create section
       $tableWord = new PhpWord();
       $section = $tableWord->addSection();
       $table = $section->addTable(['borderSize' => 1, 'borderColor' => 'ABABAB', 'cellMarginRight' => '0']);

       //header if array is not empty
       if (count($recoLists)) {
           $table->addRow(400, ['tblHeader' => true]);
           $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(10.00), $styleHeaderCell)->addText($this->anrTranslate('Recommendation'), $styleHeaderFont, $alignCenter);
           $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(2.00), $styleHeaderCell)->addText($this->anrTranslate('Imp.'), $styleHeaderFont, $alignCenter);
           $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(5.00), $styleHeaderCell)->addText($this->anrTranslate('Comment'), $styleHeaderFont, $alignCenter);
           $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(4.00), $styleHeaderCell)->addText($this->anrTranslate('Manager'), $styleHeaderFont, $alignCenter);
           $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(3.00), $styleHeaderCell)->addText($this->anrTranslate('Deadline'), $styleHeaderFont, $alignCenter);
       }

       $previousRecoId = null;

       //$alreadySet = [];
       foreach ($recoLists as $recoRisk) {
               $importance = '';
               for ($i = 0; $i <= ($recoRisk->importance - 1); $i++) {
                   $importance .= '●';
               }

               if ($recoRisk->duedate == null) {
                   $recoDeadline = '';
                   }
                   else {
                  $recoDeadline = $recoRisk->duedate->format('d-m-Y');
                   }

                   $table->addRow(400);
                   $cellRecoName = $table -> addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(5.00), $styleContentCell);
                   $cellRecoNameRun = $cellRecoName->addTextRun($styleContentCell);
                   $cellRecoNameRun -> addText(_WT($recoRisk->code) . '<w:br/>', $styleContentFontBold);
                   $cellRecoNameRun -> addText(_WT($recoRisk->description), $styleContentFont);
                   $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(2.00), $styleContentCell)->addText($importance, $styleContentFontRed, $alignCenter);
                   $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(5.00), $styleContentCell)->addText(_WT($recoRisk->comment), $styleContentFont, $alignLeft);
                   $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(4.00), $styleContentCell)->addText(_WT($recoRisk->responsable), $styleContentFont, $alignCenter);
                   $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(3.00), $styleContentCell)->addText($recoDeadline, $styleContentFont, $alignCenter);

                 $previousRecoId = $recoRisk->id;
           }

       return $this->getWordXmlFromWordObject($tableWord);
   }

     /**
     * Generates the Implamentation Recommendations Plan data
     * @param Anr $anr The ANR object
     * @param bool $full Whether or not the full plan is requested or just an extract
     * @return mixed|string The WordXml data generated
     */
     protected function generateTableImplementationHistory($anr)
     {
        /** @var AnrRecommandationHistoricService $recommandationHistoricService */
            $recommandationHistoricService = $this->recommandationHistoricService;
        $recoRecords = $recommandationHistoricService->getDeliveryRecommandationsHistory($anr->id);

        //css
        $styleHeaderCell = ['valign' => 'center', 'bgcolor' => 'DFDFDF', 'size' => 10];
        $styleHeaderFont = ['bold' => true, 'size' => 10];
        $styleContentCell = ['align' => 'left', 'valign' => 'center', 'size' => 10];
        $styleContentFont = ['bold' => false, 'size' => 10];
        $styleContentFontBold = ['bold' => true, 'size' => 10];
        $alignCenter = ['Alignment' => 'center', 'spaceAfter' => '0'];
        $alignLeft = ['Alignment' => 'left', 'spaceAfter' => '0'];
        $styleContentFontRed = ['bold' => true, 'color' => 'FF0000', 'size' => 12];

        //create section
        $tableWord = new PhpWord();
        $section = $tableWord->addSection();
        $table = $section->addTable(['borderSize' => 1, 'borderColor' => 'ABABAB', 'cellMarginRight' => '0']);

        //header if array is not empty
        if ($recoRecords) {
            $table->addRow(400, ['tblHeader' => true]);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(3.00), $styleHeaderCell)->addText($this->anrTranslate('By'), $styleHeaderFont, $alignCenter);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(6.00), $styleHeaderCell)->addText($this->anrTranslate('Recommendation'), $styleHeaderFont, $alignCenter);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(8.00), $styleHeaderCell)->addText($this->anrTranslate('Risk'), $styleHeaderFont, $alignCenter);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(4.50), $styleHeaderCell)->addText($this->anrTranslate('Implementation comment'), $styleHeaderFont, $alignCenter);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(1.75), $styleHeaderCell)->addText($this->anrTranslate('Risk before'), $styleHeaderFont, $alignCenter);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(1.75), $styleHeaderCell)->addText($this->anrTranslate('Risk after'), $styleHeaderFont, $alignCenter);
        }

        $previousRecoId = null;

        //$alreadySet = [];
        foreach ($recoRecords as $recoRecord) {
                $importance = '';
                for ($i = 0; $i <= ($recoRecord->recoImportance - 1); $i++) {
                    $importance .= '●';
                }

                if ($recoRecord->recoDuedate == null) {
                    $recoDeadline = '';
                    }
                    else {
                   $recoDeadline = $recoRecord->recoDuedate->format('d/m/Y');
                    }

                $recoValidationDate = $recoRecord->createdAt->format('d/m/Y');

                $KindOfTreatment = $recoRecord->riskKindOfMeasure;

                switch ($KindOfTreatment) {

                case 1:
                      $Treatment = "Reduction";
                      break;
                case 2;
                      $Treatment = "Denied";
                      break;
                case 3:
                      $Treatment = "Accepted";
                      break;
                case 4:
                      $Treatment = "Shared";
                      break;
                default:
                      $Treatment = "Not treated";
                }

                if ($recoRecord->riskColorBefore == "green")  {
                    $bgcolorRiskBefore = 'D6F107';
                } else if ($recoRecord->riskColorBefore == "orange") {
                    $bgcolorRiskBefore = 'FFBC1C';
                } else if ($recoRecord->riskMaxRiskBefore == -1){
                    $bgcolorRiskBefore = 'FFFFFF';
                }else{
                    $bgcolorRiskBefore = 'FD661F';
                }

                $styleContentCellRiskBefore = ['valign' => 'center', 'bgcolor' => $bgcolorRiskBefore, 'size' => 10];

                if ($recoRecord->riskColorAfter == "green")  {
                    $bgcolorRiskAfter = 'D6F107';
                } else if ($recoRecord->riskColorAfter == "orange") {
                    $bgcolorRiskAfter = 'FFBC1C';
                } else if ($recoRecord->riskMaxRiskAfter == -1){
                    $bgcolorRiskAfter = 'FFFFFF';
                }else{
                    $bgcolorRiskBefore = 'FD661F';
                }

                $styleContentCellRiskAfter = ['valign' => 'center', 'bgcolor' => $bgcolorRiskAfter, 'size' => 10];

                    $table->addRow(400);
                    $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(3.00), $styleContentCell)->addText(_WT($recoRecord->creator), $styleContentFont, $alignLeft);
                    $cellReco = $table -> addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(6.00), $styleContentCell);
                    $cellRecoRun = $cellReco->addTextRun($styleContentCell);
                    $cellRecoRun -> addText($importance . ' ' ,$styleContentFontRed);
                    $cellRecoRun -> addText(_WT($recoRecord->recoCode) . '<w:br/>', $styleContentFontBold);
                    $cellRecoRun -> addText(_WT($recoRecord->recoDescription) . '<w:br/>' . '<w:br/>', $styleContentFont);
                    $cellRecoRun -> addText($this->anrTranslate('Comment') . ': ' , $styleContentFontBold);
                    $cellRecoRun -> addText(_WT($recoRecord->recoComment) . '<w:br/>', $styleContentFont);
                    $cellRecoRun -> addText($this->anrTranslate('Deadline') . ': ' , $styleContentFontBold);
                    $cellRecoRun -> addText($recoDeadline . '<w:br/>', $styleContentFont);
                    $cellRecoRun -> addText($this->anrTranslate('Validation date') . ': ' , $styleContentFontBold);
                    $cellRecoRun -> addText($recoValidationDate . '<w:br/>', $styleContentFont);
                    $cellRecoRun -> addText($this->anrTranslate('Manager') . ': ' , $styleContentFontBold);
                    $cellRecoRun -> addText(_WT($recoRecord->recoResponsable), $styleContentFont);
                    $cellRisk = $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(8.00), $styleContentCell);
                    $cellRiskRun = $cellRisk->addTextRun($styleContentCell);
                    $cellRiskRun -> addText($this->anrTranslate('Asset type') . ': ' , $styleContentFontBold);
                    $cellRiskRun -> addText(_WT($recoRecord->riskAsset) . '<w:br/>', $styleContentFont);
                    $cellRiskRun -> addText($this->anrTranslate('Asset') . ': ' , $styleContentFontBold);
                    $cellRiskRun -> addText(_WT($recoRecord->riskInstance) . '<w:br/>', $styleContentFont);
                    $cellRiskRun -> addText($this->anrTranslate('Threat') . ': ' , $styleContentFontBold);
                    $cellRiskRun -> addText(_WT($recoRecord->riskThreat) . '<w:br/>', $styleContentFont);
                    $cellRiskRun -> addText($this->anrTranslate('Vulnerability') . ': ' , $styleContentFontBold);
                    $cellRiskRun -> addText(_WT($recoRecord->riskVul) . '<w:br/>', $styleContentFont);
                    $cellRiskRun -> addText($this->anrTranslate('Treatment type') . ': ' , $styleContentFontBold);
                    $cellRiskRun -> addText($this->anrTranslate($Treatment) . '<w:br/>', $styleContentFont);
                    $cellRiskRun -> addText($this->anrTranslate('Existing controls') . ': ' , $styleContentFontBold);
                    $cellRiskRun -> addText(_WT($recoRecord->riskCommentBefore) . '<w:br/>', $styleContentFont);
                    $cellRiskRun -> addText($this->anrTranslate('New controls') . ': ' , $styleContentFontBold);
                    $cellRiskRun -> addText(_WT($recoRecord->riskCommentAfter) . '<w:br/>', $styleContentFont);
                    $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(4.50), $styleContentCell)->addText(_WT($recoRecord->implComment), $styleContentFont, $alignLeft);
                    if($recoRecord->riskMaxRiskBefore != -1)
                      $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(1.75), $styleContentCellRiskBefore)->addText($recoRecord->riskMaxRiskBefore, $styleContentFontBold, $alignCenter);
                    else
                      $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(1.75), $styleContentCellRiskBefore)->addText("-", $styleContentFontBold, $alignCenter);
                    if($recoRecord->riskMaxRiskAfter != -1)
                      $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(1.75), $styleContentCellRiskAfter)->addText($recoRecord->riskMaxRiskAfter, $styleContentFontBold, $alignCenter);
                    else
                      $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(1.75), $styleContentCellRiskAfter)->addText("-", $styleContentFontBold, $alignCenter);

                  $previousRecoRecordId = $recoRecord->id;
            }

        return $this->getWordXmlFromWordObject($tableWord);
    }

    /**
    * Generates the Statement Of Applicability data
    * @param Anr $anr The ANR object
    * @return mixed|string The WordXml data generated
    */
    protected function generateTableStatementOfApplicability($anr, $referential)
    {
       /** @var SoaService $soaService */
       $soaService = $this->soaService;
       $filterMeasures['r.anr'] = $anr->id;
       $filterMeasures['r.uuid']= $referential;
       $measureService = $this->measureService;
       $measuresFiltered = $measureService->getList(1, 0, null, null, $filterMeasures);
       $measuresFilteredId = [];
       foreach ($measuresFiltered as $key) {
         array_push($measuresFilteredId,$key['uuid']);
       }
       $filterAnd['m.uuid']= [
           'op' => 'IN',
           'value' => $measuresFilteredId,
       ];
       $filterAnd['m.anr'] = $anr->id;
       $controlSoaList = $soaService->getList(1, 0, 'm.code', null, $filterAnd);

       //css
       $styleHeaderCell = ['valign' => 'center', 'bgcolor' => 'DFDFDF', 'size' => 10];
       $styleHeaderFont = ['bold' => true, 'size' => 10];
       $styleContentCell = ['align' => 'left', 'valign' => 'center', 'size' => 10];
       $styleContentCellCat = ['gridSpan' => 7, 'bgcolor' => 'DBE5F1','align' => 'left', 'valign' => 'center', 'size' => 10];
       $styleContentFont = ['bold' => false, 'size' => 10];
       $styleContentFontBold = ['bold' => true, 'size' => 10];
       $alignCenter = ['Alignment' => 'center', 'spaceAfter' => '0'];
       $alignLeft = ['Alignment' => 'left', 'spaceAfter' => '0'];
       $styleContentFontRed = ['bold' => true, 'color' => 'FF0000', 'size' => 12];

       //create section
       $tableWord = new PhpWord();
       $section = $tableWord->addSection();
       $table = $section->addTable(['borderSize' => 1, 'borderColor' => 'ABABAB', 'cellMarginRight' => '0']);

       //header if array is not empty
       if (count($controlSoaList)) {
           $table->addRow(400, ['tblHeader' => true]);
           $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(1.00), $styleHeaderCell)->addText($this->anrTranslate('Code'), $styleHeaderFont, $alignCenter);
           $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(5.00), $styleHeaderCell)->addText($this->anrTranslate('Control'), $styleHeaderFont, $alignCenter);
           $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(4.00), $styleHeaderCell)->addText($this->anrTranslate('Inclusion/Exclusion'), $styleHeaderFont, $alignCenter);
           $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(5.00), $styleHeaderCell)->addText($this->anrTranslate('Remarks/Justification'), $styleHeaderFont, $alignCenter);
           $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(5.00), $styleHeaderCell)->addText($this->anrTranslate('Evidences'), $styleHeaderFont, $alignCenter);
           $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(5.00), $styleHeaderCell)->addText($this->anrTranslate('Actions'), $styleHeaderFont, $alignCenter);
           $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(2.00), $styleHeaderCell)->addText($this->anrTranslate('Level of compliance'), $styleHeaderFont, $alignCenter);
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

           switch ($controlSoa['compliance']) {

             case 1:
               $complianceLevel = "Initial";
               $bgcolor = 'FD661F';
               break;
             case 2;
               $complianceLevel = "Managed";
               $bgcolor = 'FD661F';
               break;
             case 3:
               $complianceLevel = "Defined";
               $bgcolor = 'FFBC1C';
               break;
             case 4:
               $complianceLevel = "Quantitatively Managed";
               $bgcolor = 'FFBC1C';
               break;
             case 5:
               $complianceLevel = "Optimized";
               $bgcolor = 'D6F107';
               break;
             default:
               $complianceLevel = "Non-existent";
               $bgcolor = '';
           }
           if ($controlSoa['EX']) {
             $complianceLevel = "";
             $bgcolor = 'E7E6E6';
           }
           $styleContentCellCompliance = ['align' => 'left', 'valign' => 'center', 'bgcolor' => $bgcolor, 'size' => 10];

           if ($controlSoa['measure']->category->id != $previousCatId) {
             $table->addRow(400);
             $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(10.00), $styleContentCellCat)->addText(_WT($controlSoa['measure']->category->get('label' . $anr->language)), $styleContentFontBold, $alignLeft);
           }
           $previousCatId = $controlSoa['measure']->category->id;


           $table->addRow(400);
           $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(1.00), $styleContentCell)->addText(_WT($controlSoa['measure']->code), $styleContentFont, $alignCenter);
           $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(5.00), $styleContentCell)->addText(_WT($controlSoa['measure']->get('label' . $anr->language)), $styleContentFont, $alignLeft);
           $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(4.00), $styleContentCell)->addText(_WT($inclusion), $styleContentFont, $alignLeft);
           $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(5.00), $styleContentCell)->addText(_WT($controlSoa['remarks']), $styleContentFont, $alignLeft);
           $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(5.00), $styleContentCell)->addText(_WT($controlSoa['evidences']), $styleContentFont, $alignLeft);
           $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(5.00), $styleContentCell)->addText(_WT($controlSoa['actions']), $styleContentFont, $alignLeft);
           $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(2.00), $styleContentCellCompliance)->addText(_WT($this->anrTranslate($complianceLevel)), $styleContentFont, $alignLeft);
       }

       return $this->getWordXmlFromWordObject($tableWord);
   }

   /**
   * Generates the table risks by control in SOA
   * @param Anr $anr The ANR object
   * @return mixed|string The WordXml data generated
   */
   protected function generateTableRisksByControl($anr,$referential)
   {
      /** @var SoaService $soaService */
      $soaService = $this->soaService;
      $filterMeasures['r.anr'] = $anr->id;
      $filterMeasures['r.uuid']= $referential;
      $measureService = $this->measureService;
      $measuresFiltered = $measureService->getList(1, 0, null, null, $filterMeasures);
      $measuresFilteredId = [];
      foreach ($measuresFiltered as $key) {
        array_push($measuresFilteredId,$key['uuid']);
      }
      $filterAnd['m.uuid']= [
          'op' => 'IN',
          'value' => $measuresFilteredId,
      ];
      $filterAnd['m.anr'] = $anr->id;
      $controlSoaList = $soaService->getList(1, 0, 'm.code', null, $filterAnd);

      //css
      $styleHeaderCell = ['valign' => 'center', 'bgcolor' => 'DFDFDF', 'size' => 9];
      $styleHeaderFont = ['bold' => true, 'size' => 9];
      $styleContentCell = ['align' => 'left', 'valign' => 'center', 'size' => 9];
      $styleContentFont = ['bold' => false, 'size' => 9];
      $styleContentFontBoldCat= ['bold' => true, 'size' => 11];
      $styleContentFontBoldSubCat = ['bold' => true, 'size' => 10];
      $styleContentFontBold = ['bold' => true, 'size' => 9];
      $alignCenter = ['Alignment' => 'center', 'spaceAfter' => '0'];
      $alignLeft = ['Alignment' => 'left', 'spaceAfter' => '0'];
      $cellColSpan = ['gridSpan' => 3, 'valign' => 'center', 'bgcolor' => 'DFDFDF', 'size' => 9];
      $cellColSpan2 = ['gridSpan' => 2, 'valign' => 'center', 'bgcolor' => 'DFDFDF', 'size' => 9];
      $cellColSpan5 = ['gridSpan' => 5, 'valign' => 'center', 'bgcolor' => 'DFDFDF', 'size' => 9];
      $cellColSpan7 = ['gridSpan' => 7, 'valign' => 'center', 'bgcolor' => 'DFDFDF', 'size' => 9];
      $cellColSpan8 = ['gridSpan' => 8, 'valign' => 'center', 'bgcolor' => 'DFDFDF', 'size' => 9];
      $cellRowSpan = ['vMerge' => 'restart', 'valign' => 'center', 'bgcolor' => 'DFDFDF', 'align' => 'center', 'Alignment' => 'center'];
      $cellRowContinue = ['vMerge' => 'continue','valign' => 'center', 'bgcolor' => 'DFDFDF'];


      //create section
      $tableWord = new PhpWord();
      $section = $tableWord->addSection();

      $previousControlId = null;

      $riskOpService = $this->riskOpService;
      $riskService = $this->riskService;

      foreach ($controlSoaList as $controlSoa) {

        $amvs = [];
        $rolfRisks = [];
        foreach ($controlSoa['measure']->amvs as $amv) {
          array_push($amvs,$amv->id);
        }
        foreach ($controlSoa['measure']->rolfRisks as $rolfRisk) {
          array_push($rolfRisks,$rolfRisk->id);
        }

        $controlSoa['measure']->rolfRisks = $riskOpService->getRisksOp($anr->id, null, ['rolfRisks' => $rolfRisks, 'limit' => -1 ,'order'=>'cacheNetRisk', 'order_direction' => 'desc']);
        $controlSoa['measure']->amvs = $riskService->getRisks($anr->id, null, ['amvs' => $amvs, 'limit' => -1, 'order'=>'maxRisk', 'order_direction' => 'desc']);

        if (!empty($controlSoa['measure']->amvs) || !empty($controlSoa['measure']->rolfRisks)) {
          if ($controlSoa['measure']->uuid != $previousControlId) {
            $section->addTextBreak(1);
            $section->addText($controlSoa['measure']->code . ' - ' . _WT($controlSoa['measure']->get('label' . $anr->language)), $styleContentFontBoldCat);

            if (!empty($controlSoa['measure']->amvs)) {
                $section->addText($this->anrTranslate('Information risks'), $styleContentFontBoldSubCat);
                $tableRiskInfo = $section->addTable(['borderSize' => 1, 'borderColor' => 'ABABAB', 'cellMarginRight' => '0']);

                $tableRiskInfo->addRow(400);
                $tableRiskInfo->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(3.00), $cellRowSpan)->addText($this->anrTranslate('Asset'), $styleHeaderFont, $alignCenter);
                $tableRiskInfo->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(2.10), $cellColSpan)->addText($this->anrTranslate('Impact'), $styleHeaderFont, $alignCenter);
                $tableRiskInfo->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(4.50), $cellColSpan2)->addText(_WT($this->anrTranslate('Threat')), $styleHeaderFont, $alignCenter);
                $tableRiskInfo->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(10.00), $cellColSpan)->addText(_WT($this->anrTranslate('Vulnerability')), $styleHeaderFont, $alignCenter);
                $tableRiskInfo->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(3.00), $cellColSpan)->addText($this->anrTranslate('Current risk'), $styleHeaderFont, $alignCenter);
                $tableRiskInfo->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(3.00), $cellRowSpan)->addText($this->anrTranslate('Treatment'), $styleHeaderFont, $alignCenter);
                $tableRiskInfo->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(1.50), $cellRowSpan)->addText($this->anrTranslate('Residual risk'), $styleHeaderFont, $alignCenter);
                $tableRiskInfo->addRow(400);
                $tableRiskInfo->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(3.00), $cellRowContinue);
                $tableRiskInfo->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(0.70), $styleHeaderCell)->addText('C', $styleHeaderFont, $alignCenter);
                $tableRiskInfo->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(0.70), $styleHeaderCell)->addText('I', $styleHeaderFont, $alignCenter);
                $tableRiskInfo->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(0.70), $styleHeaderCell)->addText($this->anrTranslate('A'), $styleHeaderFont, $alignCenter);
                $tableRiskInfo->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(2.50), $styleHeaderCell)->addText($this->anrTranslate('Label'), $styleHeaderFont, $alignCenter);
                $tableRiskInfo->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(2.00), $styleHeaderCell)->addText($this->anrTranslate('Prob.'), $styleHeaderFont, $alignCenter);
                $tableRiskInfo->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(3.00), $styleHeaderCell)->addText($this->anrTranslate('Label'), $styleHeaderFont, $alignCenter);
                $tableRiskInfo->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(4.00), $styleHeaderCell)->addText($this->anrTranslate('Existing controls'), $styleHeaderFont, $alignCenter);
                $tableRiskInfo->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(3.00), $styleHeaderCell)->addText($this->anrTranslate('Qualif.'), $styleHeaderFont, $alignCenter);
                $tableRiskInfo->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(1.00), $styleHeaderCell)->addText('C', $styleHeaderFont, $alignCenter);
                $tableRiskInfo->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(1.00), $styleHeaderCell)->addText('I', $styleHeaderFont, $alignCenter);
                $tableRiskInfo->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(1.00), $styleHeaderCell)->addText($this->anrTranslate('A'), $styleHeaderFont, $alignCenter);
                $tableRiskInfo->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(3.00), $cellRowContinue);
                $tableRiskInfo->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(1.50), $cellRowContinue);
            }
            if (!empty($controlSoa['measure']->rolfRisks)) {
              $section->addText($this->anrTranslate('Operational risks'), $styleContentFontBoldSubCat);
              $tableRiskOp = $section->addTable(['borderSize' => 1, 'borderColor' => 'ABABAB', 'cellMarginRight' => '0']);

              $tableRiskOp->addRow(400);
              $tableRiskOp->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(3.00), $cellRowSpan)->addText($this->anrTranslate('Asset'), $styleHeaderFont, $alignCenter);
              $tableRiskOp->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(10.00), $cellRowSpan)->addText($this->anrTranslate('Risk description'), $styleHeaderFont, $alignCenter);
              if ($anr->showRolfBrut == 1) {
                $tableRiskOp->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(5.50), $cellColSpan7)->addText(_WT($this->anrTranslate('Inherent risk')), $styleHeaderFont, $alignCenter);
              }
              $tableRiskOp->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(15.00), $cellColSpan8)->addText(_WT($this->anrTranslate('Net risk')), $styleHeaderFont, $alignCenter);
              $tableRiskOp->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(2.00), $cellRowSpan)->addText($this->anrTranslate('Treatment'), $styleHeaderFont, $alignCenter);
              $tableRiskOp->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(2.00), $cellRowSpan)->addText($this->anrTranslate('Residual risk'), $styleHeaderFont, $alignCenter);

              $tableRiskOp->addRow(400, ['tblHeader' => true]);
              $tableRiskOp->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(3.00), $cellRowContinue);
              $tableRiskOp->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(10.00), $cellRowContinue);
              if ($anr->showRolfBrut == 1) {
                $tableRiskOp->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(1.00), $cellRowSpan)->addText(_WT($this->anrTranslate('Prob.')), $styleHeaderFont, $alignCenter);
                $tableRiskOp->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(3.50), $cellColSpan5)->addText(_WT($this->anrTranslate('Impact')), $styleHeaderFont, $alignCenter);
                $tableRiskOp->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(1.00), $cellRowSpan)->addText($this->anrTranslate('Current risk'), $styleHeaderFont, $alignCenter);
              }
              $tableRiskOp->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(1.00), $cellRowSpan)->addText(_WT($this->anrTranslate('Prob.')), $styleHeaderFont, $alignCenter);
              $tableRiskOp->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(3.50), $cellColSpan5)->addText(_WT($this->anrTranslate('Impact')), $styleHeaderFont, $alignCenter);
              $tableRiskOp->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(1.00), $cellRowSpan)->addText($this->anrTranslate('Current risk'), $styleHeaderFont, $alignCenter);
              $tableRiskOp->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(8.00), $cellRowSpan)->addText($this->anrTranslate('Existing controls'), $styleHeaderFont, $alignCenter);
              $tableRiskOp->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(2.00), $cellRowContinue);
              $tableRiskOp->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(2.00), $cellRowContinue);

              $tableRiskOp->addRow(400);
              $tableRiskOp->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(3.00), $cellRowContinue);
              $tableRiskOp->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(10.00), $cellRowContinue);
              if ($anr->showRolfBrut == 1) {
                $tableRiskOp->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(1.00), $cellRowContinue);
                $tableRiskOp->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(0.70), $cellRowSpan)->addText('R', $styleHeaderFont, $alignCenter);
                $tableRiskOp->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(0.70), $cellRowSpan)->addText('O', $styleHeaderFont, $alignCenter);
                $tableRiskOp->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(0.70), $cellRowSpan)->addText('L', $styleHeaderFont, $alignCenter);
                $tableRiskOp->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(0.70), $cellRowSpan)->addText('F', $styleHeaderFont, $alignCenter);
                $tableRiskOp->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(0.70), $cellRowSpan)->addText('P', $styleHeaderFont, $alignCenter);
                $tableRiskOp->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(1.00), $cellRowContinue);
              }
              $tableRiskOp->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(1.00), $cellRowContinue);
              $tableRiskOp->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(0.70), $cellRowSpan)->addText('R', $styleHeaderFont, $alignCenter);
              $tableRiskOp->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(0.70), $cellRowSpan)->addText('O', $styleHeaderFont, $alignCenter);
              $tableRiskOp->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(0.70), $cellRowSpan)->addText('L', $styleHeaderFont, $alignCenter);
              $tableRiskOp->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(0.70), $cellRowSpan)->addText('F', $styleHeaderFont, $alignCenter);
              $tableRiskOp->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(0.70), $cellRowSpan)->addText('P', $styleHeaderFont, $alignCenter);
              $tableRiskOp->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(1.00), $cellRowContinue);
              $tableRiskOp->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(8.00), $cellRowContinue);
              $tableRiskOp->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(2.00), $cellRowContinue);
              $tableRiskOp->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(2.00), $cellRowContinue);
            }
          }
          $previousControlId = $controlSoa['measure']->uuid;
          if (!empty($controlSoa['measure']->amvs)) {
            $impacts = ['c', 'i', 'd'];

            foreach ($controlSoa['measure']->amvs as $r) {
              foreach ($impacts as $impact) {
                $risk = $r[$impact .'_risk'];
                $bgcolor = 'FFBC1C';
                if ($r[$impact .'_risk_enabled'] == 0) {
                  $bgcolor = 'E7E6E6';
                  $r[$impact .'_risk'] = null;
                } else if ($risk == -1) {
                  $bgcolor = '';
                  $r[$impact .'_risk'] = "-";
                } else if ($risk <= $anr->seuil1) {
                  $bgcolor = 'D6F107';
                } else if ($risk > $anr->seuil2) {
                  $bgcolor = 'FD661F';
                }
                ${'styleContentCell' . ucfirst($impact)} = ['valign' => 'center', 'bgcolor' => $bgcolor, 'size' => 10];
              }

              $bgcolor = 'FFBC1C';
              if ($r['target_risk'] == -1)  {
                  $bgcolor = '';
                  $r['target_risk'] = "-";
              } else if ($r['target_risk'] <= $anr->seuil1) {
                  $bgcolor = 'D6F107';
              } else if ($r['target_risk'] > $anr->seuil2) {
                  $bgcolor = 'FD661F';
              }
              $styleContentCellTargetRisk = ['valign' => 'center', 'bgcolor' => $bgcolor, 'size' => 10];

              foreach ($r as $key => $value) {
                if ($value == -1){
                  $r[$key] = '-';
                }
              }

              switch ($r['kindOfMeasure']) {

                case 1:
                      $Treatment = "Reduction";
                      break;
                case 2;
                      $Treatment = "Denied";
                      break;
                case 3:
                      $Treatment = "Accepted";
                      break;
                case 4:
                      $Treatment = "Shared";
                      break;
                default:
                      $Treatment = "Not treated";
              }

              $tableRiskInfo->addRow(400);
              $tableRiskInfo->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(3.00), $styleContentCell)->addText($r['instanceName' . $anr->language], $styleContentFont, $alignCenter);
              $tableRiskInfo->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(0.70), $styleContentCell)->addText($r['c_impact'], $styleContentFont, $alignCenter);
              $tableRiskInfo->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(0.70), $styleContentCell)->addText($r['i_impact'], $styleContentFont, $alignCenter);
              $tableRiskInfo->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(0.70), $styleContentCell)->addText($r['d_impact'], $styleContentFont, $alignCenter);
              $tableRiskInfo->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(2.50), $styleContentCell)->addText(_WT($r['threatLabel' . $anr->language]), $styleContentFont, $alignLeft);
              $tableRiskInfo->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(2.00), $styleContentCell)->addText($r['threatRate'], $styleContentFont, $alignCenter);
              $tableRiskInfo->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(3.00), $styleContentCell)->addText(_WT($r['vulnLabel' . $anr->language]), $styleContentFont, $alignLeft);
              $tableRiskInfo->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(4.00), $styleContentCell)->addText(_WT($r['comment']), $styleContentFont, $alignLeft);
              $tableRiskInfo->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(3.00), $styleContentCell)->addText($r['vulnerabilityRate'], $styleContentFont, $alignCenter);
              $tableRiskInfo->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(1.00), $styleContentCellC)->addText($r['c_risk'], $styleContentFontBold, $alignCenter);
              $tableRiskInfo->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(1.00), $styleContentCellI)->addText($r['i_risk'], $styleContentFontBold, $alignCenter);
              $tableRiskInfo->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(1.00), $styleContentCellD)->addText($r['d_risk'], $styleContentFontBold, $alignCenter);
              $tableRiskInfo->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(3.00), $styleContentCell)->addText(_WT($this->anrTranslate($Treatment)), $styleContentFont, $alignLeft);
              $tableRiskInfo->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(1.50), $styleContentCellTargetRisk)->addText($r['target_risk'], $styleContentFontBold, $alignCenter);

            }
          }

          if (!empty($controlSoa['measure']->rolfRisks)) {
            $kindOfRisks = ['cacheBrutRisk', 'cacheNetRisk', 'cacheTargetedRisk'];

            foreach ($controlSoa['measure']->rolfRisks as $r) {
                foreach ($kindOfRisks as $risk) {
                  $bgcolor = 'FFBC1C';
                  if ($r[$risk] == -1) {
                    $bgcolor = '';
                    $r[$risk] = "-";
                  } else if ($r[$risk] <= $anr->seuilRolf1) {
                    $bgcolor = 'D6F107';
                  } else if ($r[$risk] > $anr->seuilRolf2) {
                    $bgcolor = 'FD661F';
                  }
                  ${'styleContentCell' . $risk} = ['valign' => 'center', 'bgcolor' => $bgcolor, 'size' => 10];
                }

                switch ($r['kindOfMeasure']) {
                  case 1:
                      $Treatment = "Reduction";
                      break;
                  case 2;
                      $Treatment = "Denied";
                      break;
                  case 3:
                      $Treatment = "Accepted";
                      break;
                  case 4:
                      $Treatment = "Shared";
                      break;
                  default:
                      $Treatment = "Not treated";
                }

                foreach ($r as $key => $value) {
                  if ($value == -1){
                      $r[$key] = '-';
                  }
                }

                $tableRiskOp->addRow(400);
                $tableRiskOp->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(3.00), $styleContentCell)->addText($r['instanceInfos']['name' . $anr->language], $styleContentFont, $alignCenter);
                $tableRiskOp->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(10.00), $styleContentCell)->addText(_WT($r['label' . $anr->language]), $styleContentFont, $alignLeft);
                if ($anr->showRolfBrut == 1) {
                  $tableRiskOp->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(1.00), $styleContentCell)->addText($r['brutProb'], $styleContentFont, $alignCenter);
                  $tableRiskOp->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(0.70), $styleContentCell)->addText($r['brutR'], $styleContentFont, $alignCenter);
                  $tableRiskOp->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(0.70), $styleContentCell)->addText($r['brutO'], $styleContentFont, $alignCenter);
                  $tableRiskOp->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(0.70), $styleContentCell)->addText($r['brutL'], $styleContentFont, $alignCenter);
                  $tableRiskOp->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(0.70), $styleContentCell)->addText($r['brutF'], $styleContentFont, $alignCenter);
                  $tableRiskOp->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(0.70), $styleContentCell)->addText($r['brutP'], $styleContentFont, $alignCenter);
                  $tableRiskOp->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(1.00), $styleContentCellcacheBrutRisk)->addText($r['cacheBrutRisk'], $styleContentFontBold, $alignCenter);
                }
                $tableRiskOp->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(1.00), $styleContentCell)->addText($r['netProb'], $styleContentFont, $alignCenter);
                $tableRiskOp->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(0.70), $styleContentCell)->addText($r['netR'], $styleContentFont, $alignCenter);
                $tableRiskOp->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(0.70), $styleContentCell)->addText($r['netO'], $styleContentFont, $alignCenter);
                $tableRiskOp->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(0.70), $styleContentCell)->addText($r['netL'], $styleContentFont, $alignCenter);
                $tableRiskOp->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(0.70), $styleContentCell)->addText($r['netF'], $styleContentFont, $alignCenter);
                $tableRiskOp->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(0.70), $styleContentCell)->addText($r['netP'], $styleContentFont, $alignCenter);
                $tableRiskOp->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(1.00), $styleContentCellcacheNetRisk)->addText($r['cacheNetRisk'], $styleContentFontBold, $alignCenter);
                $tableRiskOp->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(8.00), $styleContentCell)->addText(_WT($r['comment']), $styleContentFont, $alignLeft);
                $tableRiskOp->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(2.00), $styleContentCell)->addText(_WT($this->anrTranslate($Treatment)), $styleContentFont, $alignLeft);
                if ($r['cacheTargetedRisk'] == '-') {
                  $tableRiskOp->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(2.00), $styleContentCellcacheNetRisk)->addText($r['cacheNetRisk'], $styleContentFontBold, $alignCenter);
                } else {
                  $tableRiskOp->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(2.00), $styleContentCellcacheTargetedRisk)->addText($r['cacheTargetedRisk'], $styleContentFontBold, $alignCenter);
                }
            }
          }
        }
      }
      return $this->getWordXmlFromWordObject($tableWord);
  }

    /**
     * Generates the Processing Activities Record's General Informations data
     * @param Anr $anr The ANR object
     * @return mixed|string The WordXml data generated
     */
    protected function generateTableRecordGDPR($anr, $recordId)
    {
        $recordTable = $this->get('recordService')->get('table');
        $recordEntity = $recordTable->getEntity($recordId);
        //css
        $styleHeaderCell = ['valign' => 'center', 'bgcolor' => 'DFDFDF', 'size' => 10];
        $styleHeaderFont = ['bold' => true, 'size' => 10];
        $styleContentCell = ['align' => 'left', 'valign' => 'center', 'size' => 10];
        $styleContentCellCat = ['gridSpan' => 7, 'bgcolor' => 'DBE5F1','align' => 'left', 'valign' => 'center', 'size' => 10];
        $styleContentFont = ['bold' => false, 'size' => 10];
        $styleContentFontBold = ['bold' => true, 'size' => 10];
        $alignCenter = ['Alignment' => 'center', 'spaceAfter' => '0'];
        $alignLeft = ['Alignment' => 'left', 'spaceAfter' => '0'];
        $styleContentFontRed = ['bold' => true, 'color' => 'FF0000', 'size' => 12];
        $styleRowSpanCell = ['vMerge' => 'restart', 'align' => 'left', 'valign' => 'center', 'size' => 10];
        $styleRowContinueCell = ['vMerge' => 'continue', 'align' => 'left', 'valign' => 'center', 'size' => 10];


        $tableWord = new PhpWord();
        $section = $tableWord->addSection();
        $styleTable = ['borderSize' => 1, 'borderColor' => 'ABABAB', 'cellMarginRight' => '0'];
        $table = $section->addTable($styleTable);
        $table->addRow(400);
        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(4.00), $styleContentCell)->addText($this->anrTranslate('Name'), $styleContentFontBold, $alignLeft);
        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(14.00), $styleContentCell)->addText($recordEntity->get('label'.$this->currentLangAnrIndex), $styleContentFont, $alignLeft);
        $table->addRow(400);
        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(4.00), $styleContentCell)->addText($this->anrTranslate('Controller\'s name'), $styleContentFontBold, $alignLeft);
        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(14.00), $styleContentCell)->addText($recordEntity->get('controller')->get('label'), $styleContentFont, $alignLeft);
        $table->addRow(400);
        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(4.00), $styleContentCell)->addText($this->anrTranslate('Controller\'s contact'), $styleContentFontBold, $alignLeft);
        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(14.00), $styleContentCell)->addText($recordEntity->get('controller')->get('contact'), $styleContentFont, $alignLeft);
        $table->addRow(400);
        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(4.00), $styleContentCell)->addText($this->anrTranslate('Representative'), $styleContentFontBold, $alignLeft);
        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(14.00), $styleContentCell)->addText($recordEntity->get('representative'), $styleContentFont, $alignLeft);
        $table->addRow(400);
        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(4.00), $styleContentCell)->addText($this->anrTranslate('Data protection officer'), $styleContentFontBold, $alignLeft);
        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(14.00), $styleContentCell)->addText($recordEntity->get('dpo'), $styleContentFont, $alignLeft);
        $table->addRow(400);
        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(4.00), $styleContentCell)->addText($this->anrTranslate('Processing activity\'s purpose'), $styleContentFontBold, $alignLeft);
        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(14.00), $styleContentCell)->addText($recordEntity->get('purposes'), $styleContentFont, $alignLeft);
        $table->addRow(400);
        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(4.00), $styleContentCell)->addText($this->anrTranslate('Processing activity\'s description'), $styleContentFontBold, $alignLeft);
        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(14.00), $styleContentCell)->addText($recordEntity->get('description'), $styleContentFont, $alignLeft);
        $table->addRow(400);
        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(4.00), $styleContentCell)->addText($this->anrTranslate('Personal data processed'), $styleContentFontBold, $alignLeft);
        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(14.00), $styleContentCell)->addText($recordEntity->get('personalData'), $styleContentFont, $alignLeft);
        $table->addRow(400);
        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(4.00), $styleContentCell)->addText($this->anrTranslate('Retention_period'), $styleContentFontBold, $alignLeft);
        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(14.00), $styleContentCell)->addText(strftime("%d-%m-%Y", $recordEntity->get('erasure')->getTimeStamp()), $styleContentFont, $alignLeft);
        $table->addRow(400);
        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(4.00), $styleContentCell)->addText($this->anrTranslate('International transfer entity'), $styleContentFontBold, $alignLeft);
        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(14.00), $styleContentCell)->addText($recordEntity->get('idThirdCountry'), $styleContentFont, $alignLeft);
        $table->addRow(400);
        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(4.00), $styleContentCell)->addText($this->anrTranslate('International transfer data protection officer'), $styleContentFontBold, $alignLeft);
        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(14.00), $styleContentCell)->addText($recordEntity->get('dpoThirdCountry'), $styleContentFont, $alignLeft);
        $table->addRow(400);
        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(4.00), $styleContentCell)->addText($this->anrTranslate('Security measures'), $styleContentFontBold, $alignLeft);
        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(14.00), $styleContentCell)->addText($recordEntity->get('secMeasures'), $styleContentFont, $alignLeft);
        $dss = $recordEntity->get('dataSubjects');
        if(count($dss) > 0 ) {
            $table->addRow(400);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(4.00), $styleRowSpanCell)->addText($this->anrTranslate('Data subjects'), $styleHeaderFont, $alignLeft);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(14.00), $styleContentCell)->addText($dss[0], $styleContentFont, $alignLeft);
            array_splice($dss,0,1);
            foreach ($dss as $ds) {
                $table->addRow(400);
                $table->addCell(null, $styleRowContinueCell);
                $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(14.00), $styleContentCell)->addText($ds, $styleContentFont, $alignLeft);
            }
        }

        $rs = $recordEntity->get('recipients');
        if(count($rs) > 0 ) {
            $table->addRow(400);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(4.00), $styleRowSpanCell)->addText($this->anrTranslate('Recipients of data'), $styleHeaderFont, $alignLeft);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(14.00), $styleContentCell)->addText($rs[0]->get('label'), $styleContentFont, $alignLeft);
            foreach ($rs->slice(1) as $r) {
                $table->addRow(400);
                $table->addCell(null, $styleRowContinueCell);
                $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(14.00), $styleContentCell)->addText($r->get('label'), $styleContentFont, $alignLeft);
            }
        }

        return $this->getWordXmlFromWordObject($tableWord);
    }

    /**
     * Generates the Processing Activities Record's Joint Controllers data
     * @param Anr $anr The ANR object
     * @return mixed|string The WordXml data generated
     */
    protected function generateTableJointControllers($anr, $recordId)
    {
        $recordTable = $this->get('recordService')->get('table');
        $recordEntity = $recordTable->getEntity($recordId);
        $jointControllers = $recordEntity->get('jointControllers');
        //css
        $styleHeaderCell = ['valign' => 'center', 'bgcolor' => 'DFDFDF', 'size' => 10];
        $styleHeaderFont = ['bold' => true, 'size' => 10];
        $styleContentCell = ['align' => 'left', 'valign' => 'center', 'size' => 10];
        $styleContentCellCat = ['gridSpan' => 7, 'bgcolor' => 'DBE5F1','align' => 'left', 'valign' => 'center', 'size' => 10];
        $styleContentFont = ['bold' => false, 'size' => 10];
        $styleContentFontBold = ['bold' => true, 'size' => 10];
        $alignCenter = ['Alignment' => 'center', 'spaceAfter' => '0'];
        $alignLeft = ['Alignment' => 'left', 'spaceAfter' => '0'];
        $styleContentFontRed = ['bold' => true, 'color' => 'FF0000', 'size' => 12];

        //create section
        $tableWord = new PhpWord();
        $section = $tableWord->addSection();
        $styleTable = ['borderSize' => 1, 'borderColor' => 'ABABAB', 'cellMarginRight' => '0'];
        $table = $section->addTable($styleTable);

        //header if array is not empty
        if (count($jointControllers)) {
            $table->addRow(400, ['tblHeader' => true]);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(4.00), $styleHeaderCell)->addText($this->anrTranslate('Name'), $styleHeaderFont, $alignCenter);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(14.00), $styleHeaderCell)->addText($this->anrTranslate('Contact'), $styleHeaderFont, $alignCenter);
            foreach($jointControllers as $jc) {
                $table->addRow(400);
                $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(4.00), $styleContentCell)->addText($jc->get('label'), $styleContentFont, $alignLeft);
                $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(14.00), $styleContentCell)->addText($jc->get('contact'), $styleContentFont, $alignLeft);
            }
        }

        return $this->getWordXmlFromWordObject($tableWord);
    }

    /**
    * Generates the Processing Activities Record's Joint Controllers data
    * @param Anr $anr The ANR object
    * @return mixed|string The WordXml data generated
    */
    protected function generateTableProcessors($anr, $recordId)
    {
        $recordTable = $this->get('recordService')->get('table');
        $recordEntity = $recordTable->getEntity($recordId);
        $processors = $recordEntity->get('processors');
        //css
        $styleHeaderCell = ['valign' => 'center', 'bgcolor' => 'DFDFDF', 'size' => 10];
        $styleHeaderFont = ['bold' => true, 'size' => 10];
        $styleContentCell = ['gridSpan' => 2, 'align' => 'left', 'valign' => 'center', 'size' => 10];
        $styleContentCellSplit = ['align' => 'left', 'valign' => 'center', 'size' => 10];
        $styleContentFont = ['bold' => false, 'size' => 10];
        $styleContentFontBold = ['bold' => true, 'size' => 10];
        $styleRowSpanCell = ['gridSpan' => 2, 'vMerge' => 'restart', 'align' => 'left', 'valign' => 'center', 'size' => 10];
        $styleRowContinueCell = ['gridSpan' => 2, 'vMerge' => 'continue', 'align' => 'left', 'valign' => 'center', 'size' => 10];
        $alignCenter = ['Alignment' => 'center', 'spaceAfter' => '0'];
        $alignLeft = ['Alignment' => 'left', 'spaceAfter' => '0'];
        $styleContentFontRed = ['bold' => true, 'color' => 'FF0000', 'size' => 12];

        $result = '';
        foreach ($processors as $p) {
            //create section
            $tableWord = new PhpWord();
            $section = $tableWord->addSection();
            $table = $section->addTable(['borderSize' => 1, 'borderColor' => 'ABABAB', 'cellMarginRight' => '0']);
            $table->addRow(400);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(4.00), $styleContentCell)->addText($this->anrTranslate('Name'), $styleHeaderFont, $alignLeft);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(14.00), $styleContentCell)->addText($p->get('label'), $styleContentFont, $alignLeft);
            $table->addRow(400);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(4.00), $styleContentCell)->addText($this->anrTranslate('Contact'), $styleHeaderFont, $alignLeft);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(14.00), $styleContentCell)->addText($p->get('contact'), $styleContentFont, $alignLeft);
            $table->addRow(400);
            $bcs = $p->get('controllers');
            if(count($bcs) > 0 ) {
                $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(4.00), $styleRowSpanCell)->addText($this->anrTranslate('Controller on behalf this processor is working for'), $styleHeaderFont, $alignLeft);
                $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(7.00), $styleContentCellSplit)->addText($bcs[0]->get('label'), $styleContentFont, $alignLeft);
                $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(7.00), $styleContentCellSplit)->addText($bcs[0]->get('contact'), $styleContentFont, $alignLeft);
                $table->addRow(400);
                foreach ($bcs->slice(1) as $bc) {
                    $table->addCell(null, $styleRowContinueCell);
                    $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(7.00), $styleContentCellSplit)->addText($bc->get('label'), $styleContentFont, $alignLeft);
                    $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(7.00), $styleContentCellSplit)->addText($bc->get('contact'), $styleContentFont, $alignLeft);
                    $table->addRow(400);
                }
            }

            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(4.00), $styleContentCell)->addText($this->anrTranslate('Security measures'), $styleHeaderFont, $alignLeft);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(14.00), $styleContentCell)->addText($p->get('secMeasures'), $styleContentFont, $alignLeft);
            $table->addRow(400);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(4.00), $styleContentCell)->addText($this->anrTranslate('International transfer entity'), $styleHeaderFont, $alignLeft);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(14.00), $styleContentCell)->addText($p->get('idThirdCountry'), $styleContentFont, $alignLeft);
            $table->addRow(400);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(4.00), $styleContentCell)->addText($this->anrTranslate('International transfer officer'), $styleHeaderFont, $alignLeft);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(14.00), $styleContentCell)->addText($p->get('dpoThirdCountry'), $styleContentFont, $alignLeft);
            $section->addTextBreak(2);
            $result .= $this->getWordXmlFromWordObject($tableWord);
        }

        return $result;
    }

    /**
     * Generate the impacts appreciation table data
     * @param Anr $anr The ANR object
     * @return mixed|string The WordXml table data
     */

    protected function generateImpactsAppreciation($anr)
    {
        // TODO: C'est moche, optimiser
        /** @var AnrInstanceService $instanceService */
        $instanceService = $this->instanceService;
        $all_instances = $instanceService->getList(1, 0, 'position', null, ['anr' => $anr->id]);
        $instances = array_filter($all_instances, function ($in) {
            return (($in['c'] > -1 && $in['ch'] == 0) || ($in['i'] > -1 && $in['ih'] == 0) || ($in['d'] > -1 && $in['dh'] == 0));
        });

        $tableWord = new PhpWord();
        $section = $tableWord->addSection();
        $styleTable = ['borderSize' => 1, 'borderColor' => 'ABABAB', 'cellMarginRight' => '0'];
        $table = $section->addTable($styleTable);

        $styleHeaderCellSpan = ['valign' => 'center', 'bgcolor' => 'DFDFDF', 'size' => 10, 'gridSpan' => 3];
        $styleHeaderFont = ['bold' => true, 'size' => 10];
        $styleContentCell = ['align' => 'left', 'valign' => 'center', 'size' => 10];
        $styleContentFontBold =['bold' => true, 'size' => 10];
        $styleContentFont =['bold' => false, 'size' => 10];
        $alignCenter = ['Alignment' => 'center', 'spaceAfter' => '0'];
        $alignLeft = ['Alignment' => 'left', 'spaceAfter' => '0'];
        $cellRowSpan = ['vMerge' => 'restart', 'valign' => 'center'];
        $cellRowContinue = ['vMerge' => 'continue'];
        $cellColSpan = ['gridSpan' => 6, 'bgcolor' => 'dbe5f1', 'size' => 10, 'valign' => 'center', 'align' => 'center', 'Alignment' => 'center'];

        $impacts = ['c', 'i', 'd'];

        //header
        if (count($instances)) {
            $table->addRow(400, ['tblHeader' => true]);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(9.00), $styleHeaderCellSpan)->addText($this->anrTranslate('Impact'), $styleHeaderFont, $alignCenter);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(9.00), $styleHeaderCellSpan)->addText($this->anrTranslate('Consequences'), $styleHeaderFont, $alignCenter);
        }

        foreach ($instances as $i) {
            $instanceConsequences = $instanceService->getConsequences($anr->id, $i, true);

            //delete scale type C,I and D
            // set the correct order in the deliverable. not perfect but work
            $impactsConsequences = [];
            foreach ($instanceConsequences as $keyConsequence => $instanceConsequence) {
                if ($instanceConsequence['scaleImpactType'] < 4) {
                    unset($instanceConsequences[$keyConsequence]);
                    $impactsConsequences[$instanceConsequence['scaleImpactType']-1] = $instanceConsequence;
                }
                $impactsConsequences[$instanceConsequence['scaleImpactType']-1] = $instanceConsequence;
            }
            //reinitialization keys
            $instanceConsequences = array_values($instanceConsequences);
            $headerImpact = false;
            foreach ($impacts as $keyImpact => $impact) {
                $headerConsequence = false;
                foreach ($instanceConsequences as $keyConsequence => $instanceConsequence) {
                    if ($instanceConsequence[$impact . '_risk'] >= 0) {
                        if (!$headerImpact && !$headerConsequence) {
                            $table->addRow(400);
                            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(16), $cellColSpan)->addText(_WT($i['name' . $anr->language]), $styleHeaderFont, $alignLeft);
                        }
                        $table->addRow(400);
                        if (!$headerConsequence) {
                            $comment = $impactsConsequences[$keyImpact]
                            ['comments'][($i[$impact] != -1)
                                ? $i[$impact] : 0];
			    $translatedImpact = ucfirst($impact);
			    if ($impact === 'd'){
				$translatedImpact = ucfirst($this->anrTranslate('A'));
			    }
			    $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(1.00), $cellRowSpan)->addText($translatedImpact, $styleContentFontBold, $alignCenter);
			    $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(1.00), $cellRowSpan)->addText($i[$impact], $styleContentFontBold, $alignCenter);
                            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(5.00), $cellRowSpan)->addText($comment, $styleContentFont, $alignLeft);
                        } else {
                            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(1.00), $cellRowContinue);
                            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(1.00), $cellRowContinue);
                            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(5.00), $cellRowContinue);
                        }
                        $comment = $instanceConsequences[$keyConsequence]['comments'][($instanceConsequence[$impact . '_risk'] != -1) ? $instanceConsequence[$impact . '_risk'] : 0];
                        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(1.00), $styleContentCell)->addText($instanceConsequence['scaleImpactTypeDescription' . $anr->language], $styleContentFontBold, $alignCenter);
                        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(1.00), $styleContentCell)->addText($instanceConsequence[$impact . '_risk'], $styleContentFontBold, $alignCenter);
                        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(7.00), $styleContentCell)->addText($comment, $styleContentFont, $alignLeft);

                        $headerConsequence = true;
                    }
                }

                $headerImpact = true;
            }
        }

        return $this->getWordXmlFromWordObject($tableWord);
    }

    /**
     * Generate the threats table data
     * @param Anr $anr The ANR object
     * @param bool $fullGen Whether or not to generate the full table (all but normal) or just the normal threats
     * @return mixed|string The WordXml generated data
     */
    protected function generateThreatsTable($anr, $fullGen = false)
    {
        $threats = $this->threatService->getList(1, 0, null, null, ['anr' => $anr->id]);

        $tableWord = new PhpWord();
        $section = $tableWord->addSection();
        $styleTable = array('borderSize' => 1, 'borderColor' => 'ABABAB', 'align' => 'center', 'cellMarginRight' => '0');
        $table = $section->addTable($styleTable);

        $styleHeaderCell = array('valign' => 'center', 'bgcolor' => 'DFDFDF', 'size' => 10);
        $styleHeaderFont = array('bold' => true, 'size' => 10);

        $styleContentCell = array('align' => 'left', 'valign' => 'center', 'size' => 10);
        $styleContentCellCenter = array('align' => 'center', 'valign' => 'center', 'size' => 10);
        $styleContentFont = array('bold' => false, 'size' => 10);
        $styleContentParagraphCenter = array('Alignment' => 'center', 'spaceAfter' => '0');
        $styleContentParagraphLeft = array('Alignment' => 'left', 'spaceAfter' => '0');

        $nbThreats = 0;
        foreach ($threats as $threat) {
            if (($threat['trend'] > 0 && $threat['trend'] != 2) || $fullGen) {
                $nbThreats++;
            }
        }

        if ($nbThreats) {
            $table->addRow(400, ['tblHeader' => true]);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(7.60), $styleHeaderCell)->addText($this->anrTranslate('Threat'), $styleHeaderFont, $styleContentParagraphCenter);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(1.50), $styleHeaderCell)->addText($this->anrTranslate('CIA'), $styleHeaderFont, $styleContentParagraphCenter);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(1.70), $styleHeaderCell)->addText($this->anrTranslate('Tend.'), $styleHeaderFont, $styleContentParagraphCenter);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(1.60), $styleHeaderCell)->addText($this->anrTranslate('Prob.'), $styleHeaderFont, $styleContentParagraphCenter);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(6.60), $styleHeaderCell)->addText($this->anrTranslate('Comment'), $styleHeaderFont, $styleContentParagraphCenter);
        }

        foreach ($threats as $threat) {
            if (($threat['trend'] != 1) || $fullGen) { // All but normal
                $table->addRow(400);
                $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(5.85), $styleContentCell)->addText(_WT($threat['label' . $anr->language]), $styleContentFont, $styleContentParagraphLeft);

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
                $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(1.50), $styleContentCellCenter)->addText($cid, $styleContentFont, $styleContentParagraphCenter);

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
                $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(1.70), $styleContentCellCenter)->addText($trend, $styleContentFont, $styleContentParagraphCenter);

                // Pre-Q
                $qual = $threat['qualification'] >= 0 ? $threat['qualification'] : '';
                $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(1.60), $styleContentCellCenter)->addText($qual, $styleContentFont, $styleContentParagraphCenter);
                $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(6.60), $styleContentCellCenter)->addText(_WT($threat['comment']), $styleContentFont, $styleContentParagraphLeft);
            }
        }

        return $this->getWordXmlFromWordObject($tableWord);
    }

    /**
     * Retrieves the company name to display within the document
     * @return string The company name
     */
    protected function getCompanyName()
    {
        $client = current($this->clientTable->fetchAll());
        return $client['name'];
    }

    /**
     * Generates WordXml data from HTML.
     * @param string $input HTML input
     * @return string WordXml data
     */
    protected function generateWordXmlFromHtml($input)
    {
        // Process trix caveats
        $input = str_replace(
            ['<br>', '<div>', '</div>'],
            ['<br/>', '', ''],
            $input
        );

        // Turn it into word data
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        \PhpOffice\PhpWord\Shared\Html::addHtml($section, $input);
        return (
        str_replace(
            ['w:val="'],
            ['w:val="1'],
            $this->getWordXmlFromWordObject($phpWord, true))
        );
    }

    /**
       * Generates the instances tree
       * @param elements $elements instances risks array
       * @param parentId $parentId id of parent_Root
       * @return array
       */
    protected function buildTree($elements, $parentId) {
      $branch = [];
      foreach ($elements as $element => $value)	{
          if ($value['parent'] == $parentId) {
              $children = $this->buildTree($elements, $element);
              if ($children) {
                usort($children, function($a, $b) {
                    return $a['position'] <=> $b['position'];
                });
                $value['children'] = $children;
              }
                $branch[] = $value;

          }elseif(!isset($value['parent']) && $parentId == $element){
            $branch[]=$value;
          }
      }
      usort($branch, function($a, $b) {
          return $a['position'] <=> $b['position'];
      });
      return $branch;
    }

    /**
       * Generates a single-level array from multilevel array
       * @param multi_level_array $$multi_level_array
       * @return array
       */
    protected function single_level_array($multi_level_array){
        foreach ($multi_level_array as $a ) {
            if (isset($a['children'])) {
              $single_level_array[]= $a;
              $children_array = $this->single_level_array($a['children']);
              foreach($children_array as $children){
                  $single_level_array[]= $children;
              }
            }else{
              $single_level_array[]= $a;
            }
          }
      return $single_level_array;
    }

    /**
     * Retrieves the WordXml data from a generated PhpWord Object
     * @param PhpWord $phpWord The PhpWord Object
     * @param bool $useBody Whether to keep the entire <w:body> tag or just <w:r>
     * @return string The WordXml data
     */
    protected function getWordXmlFromWordObject($phpWord, $useBody = true)
    {
        $part = new \PhpOffice\PhpWord\Writer\Word2007\Part\Document();
        $part->setParentWriter(new Word2007($phpWord));
        $docXml = $part->write();
        $matches = [];

        if ($useBody === true) {
            $regex = '/<w:body>(.*)<w:sectPr>/is';
        } else if ($useBody === 'graph') {
            return $docXml;
        } else {
            $regex = '/<w:r>(.*)<\/w:r>/is';
        }

        if (preg_match($regex, $docXml, $matches) === 1) {
            return $matches[1];
        } else {
            return "";
        }
    }
}


function _WT($input)
{
    // Html::addHtml do that
    return str_replace(['&quot;', '&amp;lt', '&amp;gt', '&amp;'], ['"', '_lt_', '_gt_', '_amp_'], htmlspecialchars(trim($input), ENT_COMPAT, 'UTF-8'));
}
