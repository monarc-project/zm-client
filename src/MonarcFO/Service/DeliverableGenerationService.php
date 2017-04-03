<?php
/**
 * @link      https://github.com/CASES-LU for the canonical source repository
 * @copyright Copyright (c) Cases is a registered trademark of SECURITYMADEIN.LU
 * @license   MyCases is licensed under the GNU Affero GPL v3 - See license.txt for more information
 */

namespace MonarcFO\Service;

use MonarcCore\Service\DeliveriesModelsService;
use MonarcCore\Service\QuestionChoiceService;
use MonarcCore\Service\QuestionService;
use MonarcFO\Model\Entity\Anr;
use MonarcFO\Model\Entity\Object;
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
    /** @var AnrCartoRiskService */
    protected $cartoRiskService;
    /** @var InstanceRiskTable */
    protected $instanceRiskTable;
    /** @var InstanceRiskOpTable */
    protected $instanceRiskOpTable;
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
        // Find the model to use
        $model = current($this->deliveryModelService->get("table")->getEntityByFields(['category' => $typeDoc]));
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

        if (!file_exists($model->get('path' . $anr->language))) {
            if (!file_exists('./data/monarc/models')) {
                $oldumask = umask(0);
                mkdir('./data/monarc/models', 0775, true);
                umask($oldumask);
            }
            file_put_contents($model->get('path' . $anr->language), $model->get('content' . $anr->language));
        }

        $this->currentLangAnrIndex = $anr->language;

        $values = array_merge_recursive($values, $this->buildValues($anr, $model->get('category')));
        $values['txt']['TYPE'] = $this->getModelType($model->get('category'));
        return $this->generateDeliverableWithValuesAndModel($model->get('path' . $anr->language), $values);
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
                $word->setHtml($key, $value);
            }
        }

        $pathTmp = "data/" . uniqid("", true) . "_" . microtime(true) . ".docx";
        $word->saveAs($pathTmp);

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
            default:
                return 'N/A';
        }
    }

    /**
     * Builds the values to fill in the word document
     * @param Anr $anr The ANR object
     * @param int $modelCategory The model type
     * @return array The values for the Word document as a key-value array
     */
    protected function buildValues($anr, $modelCategory)
    {
        switch ($modelCategory) {
            case 1:
                return $this->buildContextValidationValues($anr);
            case 2:
                return $this->buildContextModelingValues($anr);
            case 3:
                return $this->buildRiskAssessmentValues($anr);
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

        $styleTable = ['borderSize' => 1, 'borderColor' => 'ABABAB', 'align' => 'center'];

        $tableWord = new PhpWord();
        $section = $tableWord->addSection();
        $table = $section->addTable($styleTable);

        $styleHeaderCell = ['valign' => 'center', 'bgcolor' => 'DFDFDF', 'size' => 10];
        $styleHeaderFont = ['bold' => true, 'size' => 10, 'alignment' => 'center'];

        $styleContentCell = ['align' => 'left', 'size' => 10];
        $styleContentFont = ['bold' => false, 'size' => 10];

        $cellRowSpan = ['vMerge' => 'restart', 'valign' => 'center', 'bgcolor' => 'DFDFDF', 'align' => 'center', 'Alignment' => 'center'];
        $cellRowContinue = ['vMerge' => 'continue', 'bgcolor' => 'DFDFDF'];
        $cellColSpan = ['gridSpan' => 3, 'bgcolor' => 'DFDFDF', 'size' => 10, 'valign' => 'center', 'align' => 'center', 'Alignment' => 'center'];

        $table->addRow(400);

        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(2.00), $cellRowSpan)->addText($this->anrTranslate('Lvl.'), $styleHeaderFont);
        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(8.40), $cellColSpan)->addText($this->anrTranslate('Impact'), $styleHeaderFont, ['Alignment' => 'center']);
        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(8.60), $cellRowSpan)->addText($this->anrTranslate('Consequences'), $styleHeaderFont, ['Alignment' => 'center']);

        // Manually add C/I/D impacts columns
        $table->addRow();
        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(2.00), $cellRowContinue);
        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(2.80), $styleHeaderCell)->addText('C', null, $styleHeaderFont);
        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(2.80), $styleHeaderCell)->addText('I', null, $styleHeaderFont);
        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(2.80), $styleHeaderCell)->addText('D', null, $styleHeaderFont);
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

                $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(2.80), $cellRowSpan)->addText(_WT($commentText), $styleContentFont, ['Alignment' => 'left']);
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

                $typeLabel = substr($impactType['label' . $anr->language], 0, 1);

                $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(2.80), $styleContentCell)->addText(_WT($typeLabel . ' : ' . $commentText), $styleContentCell, ['Alignment' => 'left']);
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
        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(2.00), $styleHeaderCell)->addText(_WT($this->anrTranslate('Level')), $styleHeaderFont, ['Alignment' => 'center']);
        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(17.00), $styleHeaderCell)->addText(_WT($this->anrTranslate('Comment')), $styleHeaderFont, ['Alignment' => 'center']);

        // Fill in each row
        for ($row = $threatsScale['min']; $row <= $threatsScale['max']; ++$row) {
            $table->addRow(400);

            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(2.00), $styleContentCell)->addText($row, $styleContentFont, ['Alignment' => 'center']);

            // Find the appropriate comment
            $commentText = '';
            foreach ($threatsComments as $comment) {
                if ($comment['val'] == $row) {
                    $commentText = $comment['comment' . $anr->language];
                    break;
                }
            }

            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(17.00), $styleContentCell)->addText(_WT($commentText), $styleContentFont, ['Alignment' => 'left']);
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
        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(2.00), $styleHeaderCell)->addText(_WT($this->anrTranslate('Level')), $styleHeaderFont, ['Alignment' => 'center']);
        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(17.00), $styleHeaderCell)->addText(_WT($this->anrTranslate('Comment')), $styleHeaderFont, ['Alignment' => 'center']);


        // Fill in each row
        for ($row = $vulnsScale['min']; $row <= $vulnsScale['max']; ++$row) {
            $table->addRow(400);

            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(2.00), $styleContentCell)->addText($row, $styleContentFont, ['Alignment' => 'center']);

            // Find the appropriate comment
            $commentText = '';
            foreach ($vulnsComments as $comment) {
                if ($comment['val'] == $row) {
                    $commentText = $comment['comment' . $anr->language];
                    break;
                }
            }

            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(17.00), $styleContentCell)->addText(_WT($commentText), $styleContentFont, ['Alignment' => 'left']);
        }

        $values['txt']['SCALE_VULN'] = $this->getWordXmlFromWordObject($tableWord);
        unset($tableWord);

        // Generate risks table
        $tableWord = new PhpWord();
        $section = $tableWord->addSection();
        $table = $section->addTable(['align' => 'center']);

        $risksTableCellStyle        = ['alignment' => 'center', 'valign' => 'center', 'BorderSize' => 20, 'BorderColor' => 'FFFFFF', 'BgColor' => 'FFFFFF'];
        $risksTableGreenCellStyle   = ['alignment' => 'center', 'valign' => 'center', 'BorderSize' => 20, 'BorderColor' => 'FFFFFF', 'BgColor' => 'D6F107'];
        $risksTableOrangeCellStyle  = ['alignment' => 'center', 'valign' => 'center', 'BorderSize' => 20, 'BorderColor' => 'FFFFFF', 'BgColor' => 'FFBC1C'];
        $risksTableRedCellStyle     = ['alignment' => 'center', 'valign' => 'center', 'BorderSize' => 20, 'BorderColor' => 'FFFFFF', 'BgColor' => 'FD661F'];
        $risksTableFontStyleBlack   = ['alignment' => 'center', 'bold' => true, 'color' => '000000'];
        $risksTableFontStyleWhite   = ['alignment' => 'center', 'bold' => true, 'color' => 'FFFFFF'];
        $alignCenter                = ['align' => 'center'];

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

        $size = 13 / (count($header) + 1); // 15cm
        $table->addRow(\PhpOffice\Common\Font::centimeterSizeToTwips($size));
        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips($size), $risksTableCellStyle)->addText(' ', $risksTableFontStyleBlack, $alignCenter);
        foreach ($header as $MxV) {
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips($size), $risksTableCellStyle)->addText($MxV, $risksTableFontStyleBlack, $alignCenter);
        }

        for ($row = $impactsScale['min']; $row <= $impactsScale['max']; ++$row) {
            $table->addRow(\PhpOffice\Common\Font::centimeterSizeToTwips($size));
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips($size), $risksTableCellStyle)->addText($row, $risksTableFontStyleBlack, $alignCenter);

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

                $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips($size), $style)->addText($MxV * $row, $fontStyle, $alignCenter);
            }
        }




        $values['txt']['TABLE_RISKS'] = $this->getWordXmlFromWordObject($tableWord);
        unset($tableWord);

        // Table which represents "particular attention" threats
        $values['txt']['TABLE_THREATS'] = $this->generateThreatsTable($anr, false);

        // Figure A: Trends (Questions / Answers)
        $questions = $this->questionService->getList(1, 0, null, null, ['anr' => $anr->id]);
        $questionsChoices = $this->questionChoiceService->getList(1, 0, null, null, ['anr' => $anr->id]);

        $tableWord = new PhpWord();
        $section = $tableWord->addSection();
        $table = $section->addTable(['borderSize' => 0, 'borderColor' => 'FFFFFF']);


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
                $table->addCell(11000, $styleHeaderCell)->addText(_WT($question['label' . $anr->language]), $styleHeaderFont, ['Alignment' => 'left', 'align' => 'start']);
                $table->addRow(800);
                $table->addCell(11000, $styleContentCell)->addText(_WT($response), $styleContentFont, ['Alignment' => 'left', 'align' => 'start']);
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

            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(3.00), $styleHeaderCell)->addText($this->anrTranslate("Date"), $styleHeaderFont);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(8.00), $styleHeaderCell)->addText($this->anrTranslate("Department / People"), $styleHeaderFont);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(8.00), $styleHeaderCell)->addText($this->anrTranslate("Contents"), $styleHeaderFont);
        }

        // Fill in each row
        foreach ($interviews as $interview) {
            $table->addRow(400);

            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(3.00), $styleContentCell)->addText(_WT($interview['date']), $styleContentFont, ['Alignment' => 'left']);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(8.00), $styleContentCell)->addText(_WT($interview['service']), $styleContentFont, ['Alignment' => 'left']);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(8.00), $styleContentCell)->addText(_WT($interview['content']), $styleContentFont, ['Alignment' => 'left']);
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

        $risksPlan = $this->generateRisksPlan($anr);

        $values['txt']['CURRENT_RISK_MAP'] = $this->generateCurrentRiskMap($anr, 'real');
        $values['txt']['TARGET_RISK_MAP'] = $this->generateCurrentRiskMap($anr, 'targeted');

        $values['txt']['RISKS_RECO'] = $risksPlan;
        $values['txt']['RISKS_RECO_FULL'] = $risksPlan;
        $values['txt']['TABLE_AUDIT_INSTANCES'] = $this->generateTableAudit($anr);
        $values['txt']['TABLE_AUDIT_RISKS_OP'] = $this->generateTableAuditOp($anr);

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
        $risksTableFontStyleWhite   = ['alignment' => 'center', 'bold' => true, 'color' => 'FFFFFF'];
        $alignCenter                = ['align' => 'center'];
        $alignLeft                  = ['align' => 'left'];

        /** @var AnrCartoRiskService $cartoRiskService */
        $cartoRiskService = $this->get('cartoRiskService');
        $cartoRisk = ($type == 'real') ? $cartoRiskService->getCartoReal($anr->id) : $cartoRiskService->getCartoTargeted($anr->id);

        //header
        if (isset($cartoRisk['MxV'])) {

            // Generate risks table
            $tableWord = new PhpWord();
            $section = $tableWord->addSection();
            $table = $section->addTable(['align' => 'center']);

            $header = $cartoRisk['MxV'];
            $size = 13 / (count($header) + 1); // 15cm
            $table->addRow(\PhpOffice\Common\Font::centimeterSizeToTwips($size));
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
                $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips($size), $risksTableCellStyle)->addText($impact, $risksTableFontStyleBlack, $alignCenter);

                foreach ($header as $MxV) {
			                   
			$value = $MxV * $impact;
			
                   	$result = $cartoRisk['counters'][$impact][$MxV] ?? 0;
			
			
                    if ($value <= $anr->seuil1) {
                        $style = $risksTableGreenCellStyle;
                        $fontStyle = $risksTableFontStyleBlack;
                        if ($result) {
                            $nbLow += $result;
                        }
                    } else if ($value <= $anr->seuil2) {
                        $style = $risksTableOrangeCellStyle;
                        $fontStyle = $risksTableFontStyleBlack;
                        if ($result) {
                            $nbMedium += $result;
                        }
                    } else {
                        $style = $risksTableRedCellStyle;
                        $fontStyle = $risksTableFontStyleWhite;
                        if ($result) {
                            $nbHigh += $result;
                        }
                    }
                    $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips($size), $style)->addText($result, $fontStyle, $alignCenter);
                }
            } 


            $risksTableGreenCellStyle   = ['alignment' => 'center', 'valign' => 'center', 'BgColor' => 'D6F107'];
            $risksTableGreenCellStyle2  = ['alignment' => 'center', 'valign' => 'center', 'BgColor' => 'f0f7b2'];
            $risksTableOrangeCellStyle  = ['alignment' => 'center', 'valign' => 'center', 'BgColor' => 'FFBC1C'];
            $risksTableOrangeCellStyle2 = ['alignment' => 'center', 'valign' => 'center', 'BgColor' => 'fcdd94'];
            $risksTableRedCellStyle     = ['alignment' => 'center', 'valign' => 'center', 'BgColor' => 'FD661F'];
            $risksTableRedCellStyle2    = ['alignment' => 'center', 'valign' => 'center', 'BgColor' => 'fcb28f'];

            //legend
            $maxSize = 6;
            $total = $nbLow + $nbMedium + $nbHigh;
            $lowSize = ($total) ? ($maxSize * $nbLow) / $total : 0;
            $mediumSize = ($total) ? ($maxSize * $nbMedium) / $total : 0;
            $highSize = ($total) ? ($maxSize * $nbHigh) / $total : 0;

            $tableLegend = $section->addTable();
            $tableLegend->addRow(\PhpOffice\Common\Font::centimeterSizeToTwips(0.5));
            $tableLegend->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(5))->addText($nbLow . ' ' . $this->anrTranslate('low risks'), $risksTableFontStyleBlack, $alignLeft);
            $tableLegend->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips($lowSize), $risksTableGreenCellStyle);
	if(($maxSize - $lowSize) != 0)  
            $tableLegend->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips($maxSize - $lowSize), $risksTableGreenCellStyle2);

            $tableLegend = $section->addTable();
            $tableLegend->addRow(\PhpOffice\Common\Font::centimeterSizeToTwips(0.5));
            $tableLegend->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(5))->addText($nbMedium . ' ' . $this->anrTranslate('medium risks'), $risksTableFontStyleBlack, $alignLeft);
            $tableLegend->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips($mediumSize), $risksTableOrangeCellStyle);
	if(($maxSize - $mediumSize) != 0)   
            $tableLegend->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips($maxSize - $mediumSize), $risksTableOrangeCellStyle2);

            $tableLegend = $section->addTable();
            $tableLegend->addRow(\PhpOffice\Common\Font::centimeterSizeToTwips(0.5));
            $tableLegend->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(5))->addText($nbHigh . ' ' . $this->anrTranslate('high risks'), $risksTableFontStyleBlack, $alignLeft);
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

        if (is_array($distrib) && count($distrib) > 0) {
            $gridmax = ceil(max($distrib) / 10) * 10;

            $canvas = new \Imagick();
            $canvas->newImage(400, 200, "white");
            $canvas->setImageFormat("png");
            $draw = new \ImagickDraw();

            $draw->setFontSize(10);
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
            $draw->annotation(2, 8, $gridmax);
            $draw->annotation(2, 53, ceil($gridmax - (1 * ($gridmax / 4))));
            $draw->annotation(2, 98, ceil($gridmax - (2 * ($gridmax / 4))));
            $draw->annotation(2, 143, ceil($gridmax - (3 * ($gridmax / 4))));

            //grille
            $draw->setStrokeColor('#DEDEDE');
            $draw->line(21, 5, 380, 5);
            $draw->line(21, 50, 380, 50);
            $draw->line(21, 95, 380, 95);
            $draw->line(21, 140, 380, 140);

            for ($i = 40; $i <= 400; $i += 20) {
                $draw->line($i, 5, $i, 184);
            }

            if (isset($distrib[2]) && $distrib[2] > 0) {
                $draw->setFillColor("#FD661F");
                $draw->setStrokeColor("transparent");
                $draw->rectangle(29, 195 - (10 + (($distrib[2] * 180) / $gridmax)), 137, 184);
            }
            $draw->setFillColor('#000000');
            $draw->annotation(34, 195, ucfirst($this->anrTranslate('high risks')));

            if (isset($distrib[1]) && $distrib[1] > 0) {
                $draw->setFillColor("#FFBC1C");
                $draw->setStrokeColor("transparent");
                $draw->rectangle(146, 195 - (10 + (($distrib[1] * 180) / $gridmax)), 254, 184);
            }
            $draw->setFillColor('#000000');
            $draw->annotation(151, 195, ucfirst($this->anrTranslate('medium risks')));

            if (isset($distrib[0]) && $distrib[0] > 0) {
                $draw->setFillColor("#D6F107");
                $draw->setStrokeColor("transparent");
                $draw->rectangle(263, 195 - (10 + (($distrib[0] * 180) / $gridmax)), 371, 184);
            }
            $draw->setFillColor('#000000');
            $draw->annotation(268, 195, ucfirst($this->anrTranslate('low risks')));

            $canvas->drawImage($draw);
            $path = "data/" . uniqid("", true) . "_riskgraph.png";
            $canvas->writeImage($path);

            $return = [
                'path' => $path,
                'options' => ['width' => 400, 'height' => 200],
            ];

            unset($canvas);

            return $return;
        } else {
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
            'm.id as mid', 'm.label' . $anr->language . ' as mlabel',
            'v.id as vid', 'v.label' . $anr->language . ' as vlabel',
            'ir.comment',
            'o.id as oid', 'o.scope'
        ])->where('ir.anr = :anrid')
            ->setParameter(':anrid', $anr->id)
            ->innerJoin('ir.instance', 'i')
            ->innerJoin('ir.threat', 'm')
            ->innerJoin('ir.vulnerability', 'v')
            ->innerJoin('i.object', 'o')
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
                    'm' => $r['mlabel'],
                    'v' => $r['vlabel'],
                    'comment' => $r['comment']
                ];
            }
        }

        if (!empty($mem_risks)) {
            $tableWord = new PhpWord();
            $section = $tableWord->addSection();
            $styleTable = ['borderSize' => 1, 'borderColor' => 'ABABAB'];
            $table = $section->addTable($styleTable);
            $styleHeaderCell = ['valign' => 'center', 'bgcolor' => '444444', 'size' => 10];
            $styleHeader2Font = ['color' => 'FFFFFF', 'size' => 10];
            $styleContentCell = ['align' => 'left', 'valign' => 'center', 'size' => 10];
            $styleContentFont = ['bold' => false, 'size' => 10];
            $cellColSpan = ['gridSpan' => 3, 'valign' => 'center', 'bgcolor' => 'DFDFDF', 'size' => 10];

            $table->addRow(400, ['tblHeader' => true]);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(5.00), $styleHeaderCell)->addText(_WT($this->anrTranslate('Threat')), $styleHeader2Font, ['Alignment' => 'center']);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(5.00), $styleHeaderCell)->addText(_WT($this->anrTranslate('Vulnerability')), $styleHeader2Font, ['Alignment' => 'center']);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(9.00), $styleHeaderCell)->addText(_WT($this->anrTranslate('Existing controls')), $styleHeader2Font, ['Alignment' => 'center']);

            foreach ($mem_risks as $data) {
                $table->addRow(400);
                $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(19.00), $cellColSpan)->addText(_WT($data['ctx']), $styleContentFont, ['Alignment' => 'left']);

                if (!empty($data['risks'])) {
                    foreach ($data['risks'] as $r) {
                        $table->addRow(400);
                        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(5.00), $styleContentCell)->addText(_WT($r['m']), $styleContentFont, ['Alignment' => 'left']);
                        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(5.00), $styleContentCell)->addText(_WT($r['v']), $styleContentFont, ['Alignment' => 'left']);
                        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(9.00), $styleContentCell)->addText(_WT($r['comment']), $styleContentFont, ['Alignment' => 'left']);
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
    public function generateTableAuditOp($anr)
    {
        $query = $this->instanceRiskOpTable->getRepository()->createQueryBuilder('ir');
        $result = $query->select([
            'ir.riskCacheLabel' . $anr->language . ' AS label', 'ir.comment AS comment',
            'i.id', 'i.name' . $anr->language . ' as name', 'IDENTITY(i.root)'
        ])->where('ir.anr = :anrid')
            ->setParameter(':anrid', $anr->id)
            ->innerJoin('ir.instance', 'i')
            ->innerJoin('i.asset', 'a')
            ->andWhere('a.type = :type')
            ->setParameter(':type', \MonarcCore\Model\Entity\AssetSuperClass::TYPE_PRIMARY)
            ->orderBy('ir.cacheNetRisk', 'DESC')
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
                    'risks' => [],
                ];
            }
            $lst[$r['id']]['risks'][] = [
                'label' => $r['label'],
                'comment' => $r['comment'],
            ];
        }
        if (!empty($lst)) {
            $tableWord = new PhpWord();
            $section = $tableWord->addSection();
            $styleTable = ['borderSize' => 1, 'borderColor' => 'ABABAB'];
            $table = $section->addTable($styleTable);
            $styleHeaderCell = ['valign' => 'center', 'bgcolor' => '444444', 'size' => 10];
            $styleHeader2Font = ['color' => 'FFFFFF', 'size' => 10];
            $styleContentCell = ['align' => 'left', 'valign' => 'center', 'size' => 10];
            $styleContentFont = ['bold' => false, 'size' => 10];
            $cellColSpan = ['gridSpan' => 2, 'valign' => 'center', 'bgcolor' => 'DFDFDF', 'size' => 10];

            $table->addRow(400, ['tblHeader' => true]);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(9.50), $styleHeaderCell)->addText(_WT($this->anrTranslate('Risk description')), $styleHeader2Font, ['Alignment' => 'center']);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(9.50), $styleHeaderCell)->addText(_WT($this->anrTranslate('Existing controls')), $styleHeader2Font, ['Alignment' => 'center']);

            foreach ($lst as $data) {
                $table->addRow(400);
                $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(19.00), $cellColSpan)->addText(_WT($data['path']), $styleContentFont, ['Alignment' => 'left']);

                if (!empty($data['risks'])) {
                    foreach ($data['risks'] as $r) {
                        $table->addRow(400);
                        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(9.50), $styleContentCell)->addText(_WT($r['label']), $styleContentFont, ['Alignment' => 'left']);
                        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(9.50), $styleContentCell)->addText(_WT($r['comment']), $styleContentFont, ['Alignment' => 'left']);
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
        ksort($recosRisks);

        //css
        $styleHeaderCell = ['valign' => 'center', 'bgcolor' => 'DFDFDF', 'size' => 10];
        $styleHeaderFont = ['bold' => true, 'size' => 10];
        $styleContentCell = ['align' => 'left', 'valign' => 'center', 'size' => 10];
        $styleContentFont = ['bold' => false, 'size' => 10];
        $styleContentFontBold = ['bold' => true, 'size' => 10];
        $alignCenter = ['Alignment' => 'center'];
        $alignLeft = ['Alignment' => 'left'];
        $alignRight = ['Alignment' => 'right'];
        $styleContentFontRed = ['bold' => true, 'color' => 'FF0000', 'size' => 15];
        $cell = ['bgcolor' => 'DBE5F1', 'size' => 10, 'valign' => 'center'];
        $cellColSpan = ['gridSpan' => 6, 'bgcolor' => 'DBE5F1', 'size' => 10, 'valign' => 'center'];

        //create section
        $tableWord = new PhpWord();
        $section = $tableWord->addSection();
        $table = $section->addTable(['borderSize' => 1, 'borderColor' => 'ABABAB']);

        //header if array is not empty
        if (count($recosRisks)) {
            $table->addRow(400, ['tblHeader' => true]);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(4.00), $styleHeaderCell)->addText($this->anrTranslate('Asset'), $styleHeaderFont, $alignCenter);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(5.00), $styleHeaderCell)->addText($this->anrTranslate('Threat'), $styleHeaderFont, $alignCenter);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(6.00), $styleHeaderCell)->addText($this->anrTranslate('Vulnerabity'), $styleHeaderFont, $alignCenter);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(6.00), $styleHeaderCell)->addText($this->anrTranslate('Vulnerability'), $styleHeaderFont, $alignCenter);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(7.00), $styleHeaderCell)->addText($this->anrTranslate('Existing controls'), $styleHeaderFont, $alignCenter);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(1.00), $styleHeaderCell)->addText($this->anrTranslate('C'), $styleHeaderFont, $alignCenter);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(1.00), $styleHeaderCell)->addText($this->anrTranslate('I'), $styleHeaderFont, $alignCenter);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(1.00), $styleHeaderCell)->addText($this->anrTranslate('A'), $styleHeaderFont, $alignCenter);
        }

        $previousRecoId = null;
        $impacts = ['c', 'i', 'd'];

        //unset
        $global = [];
        $toUnset = [];
        foreach ($recosRisks as $recoRisk) {
            if ($recoRisk->instance->object->scope == Object::SCOPE_GLOBAL) {
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
                    $bgcolor = 'FFBC1C';
                    if (($recoRisk->instanceRisk->$risk == -1) || (!$recoRisk->threat->$impact)) {
                        $bgcolor = 'E7E6E6';
                    } else if ($recoRisk->instanceRisk->$risk <= $anr->seuil1) {
                        $bgcolor = 'D6F107';
                    } else if ($recoRisk->instanceRisk->$risk > $anr->seuil2) {
                        $bgcolor = 'FD661F';
                    }
                    ${'styleContentCell' . ucfirst($impact)} = ['valign' => 'center', 'bgcolor' => $bgcolor, 'size' => 10];
                }

                $importance = '';
                for ($i = 0; $i <= ($recoRisk->recommandation->importance - 1); $i++) {
                    $importance .= '.';
                }

                if ($recoRisk->recommandation->id != $previousRecoId) {
                    $recoName = " [" . $recoRisk->recommandation->code . "]";
                    if ($recoRisk->recommandation->description) {
                        $recoName .= " - " . _WT($recoRisk->recommandation->description);
                    }

                    $table->addRow(400);
                    $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(4.00), $cell)->addText($importance, $styleContentFontRed, $alignRight);
                    $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(21.00), $cellColSpan)->addText($recoName, $styleContentFontBold, $alignLeft);
                }

                $continue = true;

                $key = $recoRisk->recommandation->id . ' - ' . $recoRisk->threat->id . ' - ' . $recoRisk->vulnerability->id . ' - ' . $recoRisk->objectGlobal->id;
                if (isset($toUnset[$key])) {
                    if (($recoRisk->instanceRisk->cacheMaxRisk < $toUnset[$key]) || (isset($alreadySet[$key]))) {
                        $continue = false;
                    } else {
                        $alreadySet[$key] = true;
                    }
                }

                if ($continue) {
                    $table->addRow(400);
                    $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(4.00), $styleContentCell)->addText(_WT($recoRisk->instance->{'name' . $anr->language}), $styleContentFont, $alignLeft);
                    $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(5.00), $styleContentCell)->addText(_WT($recoRisk->threat->{'label' . $anr->language}), $styleContentFont, $alignLeft);
                    $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(6.00), $styleContentCell)->addText(_WT($recoRisk->vulnerability->{'label' . $anr->language}), $styleContentFont, $alignLeft);
                    $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(7.00), $styleContentCell)->addText(_WT($recoRisk->instanceRisk->comment), $styleContentFont, $alignLeft);
                    $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(1.00), $styleContentCellC)->addText(_WT($this->anrTranslate('C')), $styleContentFontBold, $alignCenter);
                    $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(1.00), $styleContentCellI)->addText(_WT($this->anrTranslate('I')), $styleContentFontBold, $alignCenter);
                    $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(1.00), $styleContentCellD)->addText(_WT($this->anrTranslate('D')), $styleContentFontBold, $alignCenter);
                }
            }
            $previousRecoId = $recoRisk->recommandation->id;
        }

        return $this->getWordXmlFromWordObject($tableWord);
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
        $all_instances = $instanceService->getList(1, 0, null, null, ['anr' => $anr->id]);
        $instances = array_filter($all_instances, function ($in) {
            return (($in['c'] > -1 && $in['ch'] == 0) || ($in['i'] > -1 && $in['ih'] == 0) || ($in['d'] > -1 && $in['dh'] == 0));
        });

        $tableWord = new PhpWord();
        $section = $tableWord->addSection();
        $styleTable = ['borderSize' => 1, 'borderColor' => 'ABABAB'];
        $table = $section->addTable($styleTable);

        $styleHeaderCellSpan = ['valign' => 'center', 'bgcolor' => 'DFDFDF', 'size' => 10, 'gridSpan' => 3];
        $styleHeaderFont = ['bold' => true, 'size' => 10];
        $styleContentCell = ['align' => 'left', 'valign' => 'center', 'size' => 10];
        $styleContentFont =['bold' => false, 'size' => 10];
        $alignCenter = ['Alignment' => 'center'];
        $alignLeft = ['Alignment' => 'left'];
        $cellRowSpan = ['vMerge' => 'restart'];
        $cellRowContinue = ['vMerge' => 'continue'];
        $cellColSpan = ['gridSpan' => 6, 'bgcolor' => 'dbe5f1', 'size' => 10, 'valign' => 'center', 'align' => 'center', 'Alignment' => 'center'];

        $impacts = ['c', 'i', 'd'];

        //header
        if (count($instances)) {
            $table->addRow(400, ['tblHeader' => true]);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(7.00), $styleHeaderCellSpan)->addText($this->anrTranslate('Impact'), $styleHeaderFont, $alignCenter);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(9.00), $styleHeaderCellSpan)->addText($this->anrTranslate('Consequences'), $styleHeaderFont, $alignCenter);
        }

        foreach ($instances as $i) {
            $instanceConsequences = $instanceService->getConsequences($anr->id, $i, true);

            //delete scale type C,I and D
            $impactsConsequences = [];
            foreach ($instanceConsequences as $keyConsequence => $instanceConsequence) {
                if ($instanceConsequence['scaleImpactType'] < 4) {
                    unset($instanceConsequences[$keyConsequence]);
                    $impactsConsequences[$keyConsequence] = $instanceConsequence;
                }
                $impactsConsequences[$keyConsequence] = $instanceConsequence;
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
                            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(16), $cellColSpan)->addText($i['name' . $anr->language], $styleHeaderFont, $alignLeft);
                        }
                        $table->addRow(400);
                        if (!$headerConsequence) {
                            $comment = $impactsConsequences[$keyImpact]
                            ['comments'][($i[$impact] != -1)
                                ? $i[$impact] : 0];
                            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(1.00), $cellRowSpan)->addText(ucfirst($impact), $styleContentFont, $alignCenter);
                            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(1.00), $cellRowSpan)->addText($i[$impact], $styleContentFont, $alignCenter);
                            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(5.00), $cellRowSpan)->addText($comment, $styleContentFont, $alignLeft);
                        } else {
                            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(1.00), $cellRowContinue);
                            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(1.00), $cellRowContinue);
                            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(5.00), $cellRowContinue);
                        }
                        $comment = $instanceConsequences[$keyConsequence]['comments'][($instanceConsequence[$impact . '_risk'] != -1) ? $instanceConsequence[$impact . '_risk'] : 0];
                        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(1.00), $styleContentCell)->addText(substr($instanceConsequence['scaleImpactTypeDescription1'], 0, 1), $styleContentFont, $alignCenter);
                        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(1.00), $styleContentCell)->addText($instanceConsequence[$impact . '_risk'], $styleContentFont, $alignCenter);
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
        $styleTable = array('borderSize' => 1, 'borderColor' => 'ABABAB', 'align' => 'center');
        $table = $section->addTable($styleTable);

        $styleHeaderCell = array('valign' => 'center', 'bgcolor' => 'DFDFDF', 'size' => 10);
        $styleHeaderFont = array('bold' => true, 'size' => 10);

        $styleContentCell = array('align' => 'left', 'valign' => 'center', 'size' => 10);
        $styleContentCellCenter = array('align' => 'center', 'valign' => 'center', 'size' => 10);
        $styleContentFont = array('bold' => false, 'size' => 10);

        $nbThreats = 0;
        foreach ($threats as $threat) {
            if (($threat['trend'] > 0 && $threat['trend'] != 2) || $fullGen) {
                $nbThreats++;
            }
        }

        if ($nbThreats) {
            $table->addRow(400, ['tblHeader' => true]);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(1.75), $styleHeaderCell)->addText($this->anrTranslate('Code'), $styleHeaderFont, array('Alignment' => 'center'));
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(5.85), $styleHeaderCell)->addText($this->anrTranslate('Threat'), $styleHeaderFont, array('Alignment' => 'center'));
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(1.50), $styleHeaderCell)->addText($this->anrTranslate('CID'), $styleHeaderFont, array('Alignment' => 'center'));
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(1.70), $styleHeaderCell)->addText($this->anrTranslate('Tend.'), $styleHeaderFont, array('Alignment' => 'center'));
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(1.60), $styleHeaderCell)->addText($this->anrTranslate('Prob.'), $styleHeaderFont, array('Alignment' => 'center'));
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(6.60), $styleHeaderCell)->addText($this->anrTranslate('Comment'), $styleHeaderFont, array('Alignment' => 'center'));
        }

        foreach ($threats as $threat) {
            if (($threat['trend'] > 0 && $threat['trend'] != 2) || $fullGen) { // All but normal
                $table->addRow(400);
                $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(1.75), $styleContentCellCenter)->addText(_WT($threat['code']), $styleContentFont, array('Alignment' => 'left'));
                $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(5.85), $styleContentCell)->addText(_WT($threat['label' . $anr->language]), $styleContentFont, array('Alignment' => 'left'));

                // CID
                $cid = '';
                if ($threat['c']) {
                    $cid .= 'C';
                }
                if ($threat['i']) {
                    $cid .= 'I';
                }
                if ($threat['d']) {
                    $cid .= 'D';
                }
                $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(1.50), $styleContentCellCenter)->addText($cid, $styleContentFont, array('Alignment' => 'center'));

                // Trend
                switch ($threat['trend']) {
                    case 1:
                        $trend = '-';
                        break;
                    case 2:
                        $trend = 'n';
                        break;
                    case 3:
                        $trend = '+';
                        break;
                    case 4:
                        $trend = '++';
                        break;
                    default:
                        $trend = '';
                        break;
                }
                $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(1.70), $styleContentCellCenter)->addText($trend, $styleContentFont, array('Alignment' => 'center'));

                // Pre-Q
                $qual = $threat['qualification'] >= 0 ? $threat['qualification'] : '';
                $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(1.60), $styleContentCellCenter)->addText($qual, $styleContentFont, array('Alignment' => 'center'));
                $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(6.60), $styleContentCellCenter)->addText(_WT($threat['comment']));
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
        // Portion Copyright © Netlor SAS - 2015
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
     * Retrieves the WordXml data from a generated PhpWord Object
     * @param PhpWord $phpWord The PhpWord Object
     * @param bool $useBody Whether to keep the entire <w:body> tag or just <w:r>
     * @return string The WordXml data
     */
    protected function getWordXmlFromWordObject($phpWord, $useBody = true)
    {
        // Portion Copyright © Netlor SAS - 2015
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
