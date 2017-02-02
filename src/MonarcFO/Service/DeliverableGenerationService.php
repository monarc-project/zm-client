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
use MonarcFO\Model\Table\AnrTable;
use MonarcFO\Model\Table\ClientTable;
use MonarcFO\Model\Table\DeliveryTable;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\TemplateProcessor;
use PhpOffice\PhpWord\Writer\Word2007;

/**
 * Anr Deliverable Service
 *
 * Class AnrAssetService
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
     * Construct
     *
     * AbstractService constructor.
     * @param null $serviceFactory
     */
    public function __construct($serviceFactory = null)
    {
        if (is_array($serviceFactory)) {
            foreach ($serviceFactory as $k => $v) {
                $this->set($k, $v);
            }
        } else {
            $this->serviceFactory = $serviceFactory;
        }
    }

    /**
     * Set Language
     *
     * @param mixed $lang
     */
    public function setLanguage($lang)
    {
        $this->language = $lang;
    }

    public function anrTranslate($text){
        return $this->get('translateService')->translate($text,$this->currentLangAnrIndex);
    }

    /**
     * Get Delivery Models
     *
     * @return mixed
     */
    public function getDeliveryModels()
    {
        return $this->deliveryModelService->getList(1, 0, null, null, null);
    }

    /**
     * Get Last Deliveries
     *
     * @param $anrId
     * @param null $typeDoc
     * @return array
     */
    public function getLastDeliveries($anrId, $typeDoc = null)
    {
        /** @var DeliveryTable $table */
        $table = $this->get('table');

        //if typedoc is specify, retrieve only last delivery of typedoc else, retrieve last delivery for each typedoc
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
     * Generate Deliverable With Values
     *
     * @param $anrId
     * @param $typeDoc
     * @param $values
     * @param $data
     * @return string
     * @throws \Exception
     */
    public function generateDeliverableWithValues($anrId, $typeDoc, $values, $data)
    {
        // Find the model to use
        $model = current($this->deliveryModelService->get("table")->getEntityByFields(['category' => $typeDoc]));
        if (!$model) {
            throw new \Exception("Model `id` not found");
        }

        // Load the ANR
        $anr = $this->anrTable->getEntity($anrId);
        if (!$anr) {
            throw new \Exception("Anr `id` not found");
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
     * Generate Deliverable With Values And Model
     *
     * @param $modelPath
     * @param $values
     * @return string
     * @throws \Exception
     */
    protected function generateDeliverableWithValuesAndModel($modelPath, $values)
    {
        //verify template exist
        if (!file_exists($modelPath)) {
            throw new \Exception("Model path not found: " . $modelPath);
        }

        //create word
        $word = new TemplateProcessor($modelPath);

        if(!empty($values['txt'])){
            foreach ($values['txt'] as $key => $value) {
                $word->setValue($key, $value);
            }
        }
        if(!empty($values['img']) && method_exists($word,'setImg')){
            foreach ($values['img'] as $key => $value) {
                $word->setImg($key, $value['path'], $value['options']);
            }
        }
        if(!empty($values['html']) && method_exists($word,'setHtml')){
            foreach ($values['html'] as $key => $value) {
                $word->setHtml($key, $value);
            }
        }

        $pathTmp = "data/" . uniqid("", true) . "_" . microtime(true) . ".docx";
        $word->saveAs($pathTmp);

        if(!empty($values['img'])){
            foreach ($values['img'] as $key => $value) {
                if(file_exists($value['path'])){
                    unlink($value['path']);
                }
            }
        }

        return $pathTmp;
    }

    /**
     * Get Model Type
     *
     * @param $modelCategory
     * @return string
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
     * Build Values
     *
     * @param $anr
     * @param $modelCategory
     * @return array
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
     * Build Context Validation Values
     *
     * @param $anr
     * @return array
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
        $styleContentCellCenter = ['align' => 'center', 'size' => 10];
        $styleContentFont = ['bold' => false, 'size' => 10];
        $styleContentParag = ['align' => 'left', 'size' => 10];
        $styleContentParagCenter = ['align' => 'center', 'size' => 10];

        $cellRowSpan = ['vMerge' => 'restart', 'valign' => 'center', 'bgcolor' => 'DFDFDF', 'align' => 'center', 'Alignment' => 'center'];
        $cellRowContinue = ['vMerge' => 'continue', 'bgcolor' => 'DFDFDF'];
        $cellColSpan = ['gridSpan' => 3, 'bgcolor' => 'DFDFDF', 'size' => 10, 'valign' => 'center', 'align' => 'center', 'Alignment' => 'center'];
        $cellHCentered = ['alignment' => 'center'];
        $cellVCentered = ['valign' => 'center'];

        $table->addRow(400);

        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(2.00), $cellRowSpan)->addText($this->anrTranslate('Niv.'), $styleHeaderFont);
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
                if ($impactType['type_id'] < 4 || $impactType['isHidden']) continue;

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

        $table->addRow(400,['tblHeader'=>true]);
        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(2.00), $styleHeaderCell)->addText(_WT($this->anrTranslate('Niveau')), $styleHeaderFont, ['Alignment' => 'center']);
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

        $table->addRow(400,['tblHeader'=>true]);
        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(2.00), $styleHeaderCell)->addText(_WT($this->anrTranslate('Niveau')), $styleHeaderFont, ['Alignment' => 'center']);
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

        $risksTableCellStyle = array('alignment' => 'center', 'valign' => 'center', 'BorderSize' => 6, 'BorderColor' => 'FFFFFF', 'BgColor' => 'FFFFFF');
        $risksTableGreenCellStyle = array('alignment' => 'center', 'valign' => 'center', 'BorderSize' => 6, 'BorderColor' => 'FFFFFF', 'BgColor' => '4CAF50');
        $risksTableOrangeCellStyle = array('alignment' => 'center', 'valign' => 'center', 'BorderSize' => 6, 'BorderColor' => 'FFFFFF', 'BgColor' => 'FF9800');
        $risksTableRedCellStyle = array('alignment' => 'center', 'valign' => 'center', 'BorderSize' => 6, 'BorderColor' => 'FFFFFF', 'BgColor' => 'F44336');
        $risksTableFontStyle = array('bold' => true);
        $risksTableValueFontStyle = array('Alignment' => 'center', 'bold' => true, 'color' => 'FFFFFF');

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

        $size = 19/(count($header)+1); // 19cm
        $table->addRow(\PhpOffice\Common\Font::centimeterSizeToTwips($size));
        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips($size), $risksTableCellStyle)->addText('');
        foreach ($header as $MxV) {
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips($size), $risksTableCellStyle)->addText($MxV, $risksTableFontStyle);
        }

        for ($row = $impactsScale['min']; $row <= $impactsScale['max']; ++$row) {
            $table->addRow(\PhpOffice\Common\Font::centimeterSizeToTwips($size));
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips($size), $risksTableCellStyle)->addText($row, $risksTableFontStyle);

            foreach ($header as $MxV) {
                $value = $MxV * $row;

                if ($value <= $anr->seuil1) {
                    $style = $risksTableGreenCellStyle;
                } else if ($value <= $anr->seuil2) {
                    $style = $risksTableOrangeCellStyle;
                } else {
                    $style = $risksTableRedCellStyle;
                }

                $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips($size), $style)->addText($MxV * $row, $risksTableValueFontStyle, ['align' => 'center']);
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

        $table->addRow(400,['tblHeader'=>true]);

        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(3.00), $styleHeaderCell)->addText($this->anrTranslate("Date"), $styleHeaderFont);
        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(8.00), $styleHeaderCell)->addText($this->anrTranslate("Department / People"), $styleHeaderFont);
        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(8.00), $styleHeaderCell)->addText($this->anrTranslate("Contents"), $styleHeaderFont);

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

    protected function buildContextModelingValues($anr)
    {
        // Models are incremental, so use values from level-1 model
        $values = $this->buildContextValidationValues($anr);

        $values['html']['SYNTH_ACTIF'] = $anr->synthAct;
        $values['txt']['IMPACTS_APPRECIATION'] = $this->generateImpactsAppreciation($anr);

        return $values;
    }

    /**
     * Build Risk Assessment Values
     *
     * @param $anr
     * @return array
     */
    protected function buildRiskAssessmentValues($anr)
    {
        // Models are incremental, so use values from level-2 model
        $values = [];
        $values = array_merge($values,$this->buildContextModelingValues($anr));

        $values['html']['DISTRIB_EVAL_RISK'] = $this->getRisksDistribution($anr);

        $values['img']['GRAPH_EVAL_RISK'] = $this->generateRisksGraph($anr);

        $values['txt']['RISKS_RECO'] = $this->generateRisksPlan($anr, false);
        $values['txt']['RISKS_RECO_FULL'] = $this->generateRisksPlan($anr, true);
        $values['txt']['TABLE_AUDIT_INSTANCES'] = $this->generateTableAudit($anr);

        return $values;
    }

    /**
     * Generate Risks Graph
     *
     * @param $anr
     * @return string
     */
    protected function generateRisksGraph($anr)
    {
        $this->cartoRiskService->buildListScalesAndHeaders($anr->id);
        list($counters, $distrib) = $this->cartoRiskService->getCountersRisks('raw'); // raw = without target

        if(is_array($distrib) && count($distrib)>0){
            $gridmax = ceil(max($distrib)/10) * 10;

            $canvas = new \Imagick();
            $canvas->newImage(400, 200, "white");
            $canvas->setImageFormat("png");
            $draw = new \ImagickDraw();

            $draw->setFontSize(10);
            $draw->setStrokeAntialias(true);
            // $draw->setStrokeColor('black');

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
            $draw->annotation ( 34 , 195 , ucfirst($this->anrTranslate('risques élevés')) );

            if(isset($distrib[1]) && $distrib[1]>0){
                $draw->setFillColor("#FFBC1C");
                $draw->setStrokeColor("transparent");
                $draw->rectangle(146, 195 - (10 + (($distrib[1] * 180)/$gridmax)) , 254, 184);
            }
            $draw->setFillColor('#000000');
            $draw->annotation ( 151 , 195 , ucfirst($this->anrTranslate('medium risks')) );

            if(isset($distrib[0]) && $distrib[0]>0){
                $draw->setFillColor("#D6F107");
                $draw->setStrokeColor("transparent");
                $draw->rectangle(263, 195 - (10 + (($distrib[0] * 180)/$gridmax)) , 371, 184);
            }
            $draw->setFillColor('#000000');
            $draw->annotation ( 268 , 195 , ucfirst($this->anrTranslate('low risks')) );

            $canvas->drawImage($draw);
            $path = "data/".uniqid("", true)."_riskgraph.png";
            $canvas->writeImage($path);

            $return = [
                'path' => $path,
                'options' => ['width' => 400, 'height' => 200],
            ];

            unset($canvas);
            unset($imgWord);

            return $return;
        }
        else{
            return "";
        }

    }

    /**
     * Generate Table Audit
     *
     * @param $anr
     * @return mixed|string
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
        foreach ($result as $r) {
            if(!isset($globalObject[$r['oid']][$r['mid']][$r['vid']])){
                if (!isset($mem_risks[$r['oid']])) {
                    $mem_risks[$r['oid']] = [];
                    $mem_risks[$r['oid']]['ctx'] = $r['name'];
                    $mem_risks[$r['oid']]['risks'] = [];
                }

                $mem_risks[$r['oid']]['risks'][] = [
                    'm' => $r['mlabel'],
                    'v' => $r['vlabel'],
                    'comment' => $r['comment']
                ];

                if($r['scope'] == \MonarcCore\Model\Entity\ObjectSuperClass::SCOPE_GLOBAL){
                    $globalObject[$r['oid']][$r['mid']][$r['vid']] = $r['oid'];
                }
            }
        }

        if (!empty($mem_risks)) {
            $tableWord = new PhpWord();
            $section = $tableWord->addSection();
            $styleTable = ['borderSize' => 1, 'borderColor' => 'ABABAB'];
            $table = $section->addTable($styleTable);
            $styleHeaderCell = ['valign' => 'center', 'bgcolor' => '444444', 'size' => 10];
            $styleHeader2Font = ['color' => 'FFFFFF', 'size' => 10];
            $styleHeaderFont = ['bold' => true, 'size' => 10];
            $styleContentCell = ['align' => 'left', 'valign' => 'center', 'size' => 10];
            $styleContentFont = ['bold' => false, 'size' => 10];
            $cellColSpan = ['gridSpan' => 3, 'valign' => 'center', 'bgcolor' => 'DFDFDF', 'size' => 10];

            $table->addRow(400,['tblHeader'=>true]);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(5.00), $styleHeaderCell)->addText(_WT($this->anrTranslate('Threat')), $styleHeader2Font, ['Alignment' => 'center']);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(5.00), $styleHeaderCell)->addText(_WT($this->anrTranslate('Vulnerability')), $styleHeader2Font, ['Alignment' => 'center']);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(9.00), $styleHeaderCell)->addText(_WT($this->anrTranslate('Measures set')), $styleHeader2Font, ['Alignment' => 'center']);

            foreach ($mem_risks as $id_inst => $data) {
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
     * Get Risks Distribution
     *
     * @param $anr
     * @return string
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

        $intro = sprintf($this->anrTranslate("La liste des risques traités est fournie en fichier annexe. Il répertorie %d risque(s) dont :"), $sum);
        return $intro . '<br/><ul>' .
            '<li>' . sprintf($this->anrTranslate('%d risque(s) critique(s) à traiter en priorité'), $distrib[2]) . '</li>' .
            '<li>' . sprintf($this->anrTranslate('%d risque(s) moyen(s) à traiter partiellement'), $distrib[1]) . '</li>' .
            '<li>' . sprintf($this->anrTranslate('%d risque(s) faible(s) négligeables'), $distrib[0]) . '</li></ul>';
    }

    /**
     * Generate Risks Plan
     *
     * @param $anr
     * @param bool $full
     * @return mixed|string
     */
    protected function generateRisksPlan($anr, $full = false)
    {
        $recos = $this->recommandationService->getList(1, 0, 'position', null, ['anr' => $anr->id]);

        $tableWord = new PhpWord();
        $section = $tableWord->addSection();
        $styleTable = array('borderSize' => 1, 'borderColor' => 'ABABAB');
        $table = $section->addTable($styleTable);

        $styleHeaderCell = array('valign' => 'center', 'bgcolor' => 'DFDFDF', 'size' => 10);
        $styleHeaderFont = array('bold' => true, 'size' => 10);

        $styleContentCell = array('align' => 'left', 'valign' => 'center', 'size' => 10);
        $styleContentCellCenter = array('align' => 'center', 'valign' => 'center', 'size' => 10);
        $styleContentFont = array('bold' => false, 'size' => 10);
        $styleContentParag = array('align' => 'left', 'size' => 10);
        $styleContentParagCenter = array('align' => 'center', 'size' => 10);
        $alignCenter = ['Alignment' => 'center'];
        $styleContentFontRed = array('bold' => true, 'color' => 'FF0000', 'size' => 10);

        $table->addRow(400,['tblHeader'=>true]);
        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(7.00), $styleHeaderCell)->addText($this->anrTranslate('Measures set'), $styleHeaderFont, $alignCenter);
        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(3.50), $styleHeaderCell)->addText($this->anrTranslate('Instance'), $styleHeaderFont, $alignCenter);
        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(7.00), $styleHeaderCell)->addText($this->anrTranslate('Recommendation'), $styleHeaderFont, $alignCenter);
        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(1.50), $styleHeaderCell)->addText($this->anrTranslate('Imp.'), $styleHeaderFont, $alignCenter);

        $cpte_elem = 0;
        $max_elem = 5;

        $cellRowSpanStart = ['vMerge' => 'restart', 'valign' => 'top', 'align' => 'left', 'size' => 10];
        $cellRowSpanContinue = ['vMerge' => 'continue', 'size' => 10];

        foreach ($recos as $reco) {
            if ($cpte_elem < $max_elem || $full) {
                $cpte_elem++;
                $risks = $this->recommandationRiskService->getList(1, 0, null, null, ['anr' => $anr->id, 'recommandation' => $reco['id']]);

                if (!empty($risks)) {
                    $first = true;
                    foreach ($risks as $risk) {
                        if ($risk['instanceRisk']) {
                            $sharedInstanceRisk = $this->instanceRiskTable->get($risk['instanceRisk']->id);
                            if ($sharedInstanceRisk['kindOfMeasure'] == 5) continue;
                        } else if ($risk['instanceRiskOp']) {
                            $sharedInstanceRisk = $this->instanceRiskOpTable->get($risk['instanceRiskOp']->id);
                            if ($sharedInstanceRisk['kindOfMeasure'] == 5) continue;
                        }


                        $table->addRow(400);

                        if ($first) {
                            $cellfusion = $cellRowSpanStart;
                            $first = false;
                        } else {
                            $cellfusion = $cellRowSpanContinue;
                        }

                        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(7.00), $styleContentCell)->addText(_WT($sharedInstanceRisk['comment']), $styleContentFont, ['Alignment' => 'left']);
                        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(3.50), $styleContentCell)->addText(_WT($risk['instance']->{'name' . $anr->language}), $styleContentFont, ['Alignment' => 'left']);

                        $contentreco = "[" . $reco['code'] . "] " . _WT($reco['description']);
                        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(7.00), $cellfusion)->addText($contentreco, $styleContentFont, ['Alignment' => 'left']);

                        switch ($reco['importance']) {
                            case 0:
                                $contentreco = "";
                                break;
                            case 1:
                                $contentreco = "o";
                                break;
                            case 2:
                                $contentreco = "oo";
                                break;
                            case 3:
                                $contentreco = "ooo";
                                break;
                        }

                        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(1.50), $cellfusion)->addText(_WT($contentreco), $styleContentFontRed);
                    }
                }
            }

            if ($cpte_elem > $max_elem && !$full) {
                break;
            }
        }

        return $this->getWordXmlFromWordObject($tableWord);
    }

    /**
     * Generate Impacts Appreciation
     *
     * @param $anr
     * @return mixed|string
     */
    protected function generateImpactsAppreciation($anr)
    {
        // TODO: C'est moche, optimiser
        $all_instances = $this->instanceService->getList(1, 0, null, null, ['anr' => $anr->id]);
        $instances = array_filter($all_instances, function ($in) {
            return (($in['c'] > -1 && $in['ch'] == 0) || ($in['i'] > -1 && $in['ih'] == 0) || ($in['d'] > -1 && $in['dh'] == 0));
        });

        $tableWord = new PhpWord();
        $section = $tableWord->addSection();
        $styleTable = array('borderSize' => 1, 'borderColor' => 'ABABAB');
        $table = $section->addTable($styleTable);

        $styleHeaderCell = array('valign' => 'center', 'bgcolor' => 'DFDFDF', 'size' => 10);
        $styleHeaderFont = array('bold' => true, 'size' => 10);

        $styleContentCell = array('align' => 'left', 'valign' => 'center', 'size' => 10);
        $styleContentCellCenter = array('align' => 'center', 'valign' => 'center', 'size' => 10);
        $styleContentFont = array('bold' => false, 'size' => 10);
        $styleContentParag = array('align' => 'left', 'size' => 10);
        $styleContentParagCenter = array('align' => 'center', 'size' => 10);
        $alignCenter = ['Alignment' => 'center'];

        $table->addRow(400,['tblHeader'=>true]);
        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(13.00), $styleHeaderCell)->addText($this->anrTranslate('Label'), $styleHeaderFont, $alignCenter);
        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(2.00), $styleHeaderCell)->addText("C", $styleHeaderFont, $alignCenter);
        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(2.00), $styleHeaderCell)->addText("I", $styleHeaderFont, $alignCenter);
        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(2.00), $styleHeaderCell)->addText("D", $styleHeaderFont, $alignCenter);

        foreach ($instances as $i) {
            $table->addRow(400);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(13.00), $styleContentCell)->addText($i['name' . $anr->language], $styleContentFont, ['Alignment' => 'left']);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(2.00), $styleContentCell)->addText($i['c'], $styleContentFont, $alignCenter);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(2.00), $styleContentCell)->addText($i['i'], $styleContentFont, $alignCenter);
            $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(2.00), $styleContentCell)->addText($i['d'], $styleContentFont, $alignCenter);
        }

        return $this->getWordXmlFromWordObject($tableWord);
    }

    /**
     * Generate Threats Table
     *
     * @param $anr
     * @param bool $fullGen
     * @return mixed|string
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
        $styleContentParag = array('align' => 'left', 'size' => 10);
        $styleContentParagCenter = array('align' => 'center', 'size' => 10);

        $table->addRow(400,['tblHeader'=>true]);
        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(1.75), $styleHeaderCell)->addText($this->anrTranslate('Code'), $styleHeaderFont, array('Alignment' => 'center'));
        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(5.85), $styleHeaderCell)->addText($this->anrTranslate('Threat'), $styleHeaderFont, array('Alignment' => 'center'));
        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(1.50), $styleHeaderCell)->addText($this->anrTranslate('CID'), $styleHeaderFont, array('Alignment' => 'center'));
        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(1.70), $styleHeaderCell)->addText($this->anrTranslate('Tend.'), $styleHeaderFont, array('Alignment' => 'center'));
        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(1.60), $styleHeaderCell)->addText($this->anrTranslate('Prob.'), $styleHeaderFont, array('Alignment' => 'center'));
        $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(6.60), $styleHeaderCell)->addText($this->anrTranslate('Comment'), $styleHeaderFont, array('Alignment' => 'center'));

        foreach ($threats as $threat) {
            if (($threat['trend'] > 0 && $threat['trend'] != 2) || $fullGen) { // All but normal
                $table->addRow(400);
                $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(1.75), $styleContentCellCenter)->addText(_WT($threat['code']), $styleContentFont, array('Alignment' => 'left'));
                $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(5.85), $styleContentCell)->addText(_WT($threat['label' . $anr->language]), $styleContentFont, array('Alignment' => 'left'));

                // CID
                $cid = '';
                if ($threat['c']) $cid .= 'C';
                if ($threat['i']) $cid .= 'I';
                if ($threat['d']) $cid .= 'D';
                $table->addCell(\PhpOffice\Common\Font::centimeterSizeToTwips(1.50), $styleContentCellCenter)->addText($cid, $styleContentFont, array('Alignment' => 'center'));

                // Trend
                $trend = '';
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
     * Get Company Name
     *
     * @return mixed
     */
    protected function getCompanyName()
    {
        $client = current($this->clientTable->fetchAll());
        return $client['name'];
    }

    /**
     * Generate Word Xml Front Html
     *
     * @param $input
     * @return mixed
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
        /*$input = str_replace(
            ['<br>', '<div>', '</div>', '<blockquote>', '</blockquote>'],
            ['</p><p>', '<p>', '</p>', '<blockquote><p>', '</p></blockquote>'],
            $input);*/

        //die("errors: " . $input);

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
     * Get Word Xml From Word Object
     *
     * @param $phpWord
     * @param bool $useBody
     * @return mixed|string
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
