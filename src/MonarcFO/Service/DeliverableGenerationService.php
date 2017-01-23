<?php
namespace MonarcFO\Service;
use MonarcCore\Service\AbstractServiceFactory;
use MonarcCore\Service\DeliveriesModelsService;
use MonarcCore\Service\QuestionChoiceService;
use MonarcCore\Service\QuestionService;
use MonarcFO\Model\Entity\RecommandationRisk;
use MonarcFO\Model\Table\AnrTable;
use MonarcFO\Model\Table\ClientTable;
use MonarcFO\Model\Table\DeliveryTable;
use MonarcFO\Model\Table\InstanceTable;
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

    /**
     * Construct
     *
     * AbstractService constructor.
     * @param null $serviceFactory
     */
    public function __construct($serviceFactory = null)
    {
        if (is_array($serviceFactory)){
            foreach($serviceFactory as $k => $v){
                $this->set($k,$v);
            }
        } else {
            $this->serviceFactory = $serviceFactory;
        }
    }

    public function setLanguage($lang) {
        $this->language = $lang;
    }

    public function getDeliveryModels() {
        return $this->deliveryModelService->getList(1, 0, null, null, null);
    }

    public function getLastDeliveries($anrId, $typeDoc = null) {
        /** @var DeliveryTable $table */
        $table = $this->get('table');

        if(!empty($typeDoc)){
            $deliveries = $table->getEntityByFields(['anr' => $anrId, 'typedoc'=>$typeDoc],['createdAt'=>'DESC']);
            $lastDelivery = null;
            foreach ($deliveries as $delivery) {
                $lastDelivery = $delivery->getJsonArray();
                break;
            }
            return $lastDelivery;
        }else{
            $deliveries = $table->getEntityByFields(['anr' => $anrId],['createdAt'=>'DESC']);
            $lastDelivery = [];
            foreach ($deliveries as $delivery) {
                if(empty($lastDelivery[$delivery->get('typedoc')])){
                    $lastDelivery[$delivery->get('typedoc')] = $delivery->getJsonArray();
                }
                if(count($lastDelivery) == 3){
                    break;
                }
            }
            return array_values($lastDelivery);
        }
    }

    public function generateDeliverableWithValues($anrId, $typeDoc, $values, $data) {
        // Find the model to use
        $model = current($this->deliveryModelService->get("table")->getEntityByFields(['category'=>$typeDoc]));
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

        $dependencies =  (property_exists($this, 'dependencies')) ? $this->dependencies : [];
        $this->setDependencies($delivery, $dependencies);

        /** @var DeliveryTable $table */
        $table = $this->get('table');
        $table->save($delivery);

        if( ! file_exists($model->get('path' . $anr->language))){
            if(!file_exists('./data/monarc/models')){
                $oldumask = umask(0);
                mkdir('./data/monarc/models', 0775, true);
                umask($oldumask);
            }
            file_put_contents($model->get('path' . $anr->language) , $model->get('content'. $anr->language));
        }

        // Word-filter the input values
        foreach ($values as $key => $val) {
            if ($key != "SUMMARY_EVAL_RISK") {
                $values[$key] = _WT($val);
            } else {
                // This field comes from the frontend at deliverable generation time, so it is already in $values
                $values[$key] = $this->generateWordXmlFromHtml($val);
            }
        }

        $values = array_merge($values, $this->buildValues($anr, $model->get('category')));
        $values['TYPE'] = $this->getModelType($model->get('category'));
        return $this->generateDeliverableWithValuesAndModel($model->get('path' . $anr->language), $values);
    }

    protected function generateDeliverableWithValuesAndModel($modelPath, $values) {
        if (!file_exists($modelPath)) {
            throw new \Exception("Model path not found: " . $modelPath);
        }

        $word = new TemplateProcessor($modelPath);

        foreach ($values as $key => $value) {
            $word->setValue($key, $value);
        }

        $pathTmp = "/tmp/" . uniqid("", true) . "_" . microtime(true) . ".docx";
        $word->saveAs($pathTmp);

        return $pathTmp;
    }

    protected function getModelType($modelCategory) {
        switch ($modelCategory) {
            case 1: return 'Validation du contexte';
            case 2: return 'Validation du modèle';
            case 3: return 'Rapport final';
            default: return 'N/A';
        }
    }

    protected function buildValues($anr, $modelCategory) {
        switch ($modelCategory) {
            case 1: return $this->buildContextValidationValues($anr);
            case 2: return $this->buildContextModelingValues($anr);
            case 3: return $this->buildRiskAssessmentValues($anr);
            default: return [];
        }
    }

    protected function buildContextValidationValues($anr) {
        // Values read from database
        $values = [
            'COMPANY' => $this->getCompanyName(),
            'CONTEXT_ANA_RISK' => $this->generateWordXmlFromHtml($anr->contextAnaRisk),
            'CONTEXT_GEST_RISK' => $this->generateWordXmlFromHtml($anr->contextGestRisk),
            'SYNTH_EVAL_THREAT' => $this->generateWordXmlFromHtml($anr->synthThreat),
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

        $table->addRow();

        $table->addCell(500, $cellRowSpan)->addText('Niv.', $styleHeaderFont);
        $table->addCell(9000, $cellColSpan)->addText('Impact', $styleHeaderFont, ['Alignment' => 'center']);
        $table->addCell(9000, $cellRowSpan)->addText('Conséquences', $styleHeaderFont, ['Alignment' => 'center']);

        // Manually add C/I/D impacts columns
        $table->addRow();
        $table->addCell(null, $cellRowContinue);
        $table->addCell(3000, $styleHeaderCell)->addText('C', null, $styleHeaderFont);
        $table->addCell(3000, $styleHeaderCell)->addText('I', null, $styleHeaderFont);
        $table->addCell(3000, $styleHeaderCell)->addText('D', null, $styleHeaderFont);
        $table->addCell(null, $cellRowContinue);

        // Fill in each row
        for ($row = $impactsScale['min']; $row <= $impactsScale['max']; ++$row) {
            $cellRowSpan = ['vMerge' => 'restart', 'valign' => 'top', 'bgcolor' => 'FFFFFF'];
            $cellRowContinue = ['vMerge' => 'continue'];

            $table->addRow(400);

            $table->addCell(500, $cellRowSpan)->addText($row, $styleContentFont, ['Alignment' => 'center']);

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

                $table->addCell(3000, $cellRowSpan)->addText(_WT($commentText), $styleContentFont, ['Alignment' => 'left']);
            }

            // Then ROLFP and custom columns as rows
            $first = true;
            foreach ($impactsTypes as $impactType) {
                if ($impactType['type_id'] < 4 || $impactType['isHidden']) continue;

                if ($first) {
                    $first = false;
                } else {
                    $table->addRow();
                    $table->addCell(100, $cellRowContinue);
                    $table->addCell(100, $cellRowContinue);
                    $table->addCell(100, $cellRowContinue);
                    $table->addCell(100, $cellRowContinue);
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

                $table->addCell(3000, $styleContentCell)->addText(_WT($typeLabel . ' : ' . $commentText), $styleContentCell, ['Alignment' => 'left']);
            }
        }

        $values['SCALE_IMPACT'] = $this->getWordXmlFromWordObject($tableWord);
        unset($tableWord);

        // Generate threat scale table
        $threatsScale = current(current($this->scaleService->getList(1, 0, null, null, ['anr' => $anr->id, 'type' => 2])));
        $threatsComments = $this->scaleCommentService->getList(1, 0, null, null, ['anr' => $anr->id, 'scale' => $threatsScale['id']]);

        $tableWord = new PhpWord();
        $section = $tableWord->addSection();
        $table = $section->addTable($styleTable);

        $table->addRow(400);
        $table->addCell(80, $styleHeaderCell)->addText(_WT('Niveau'), $styleHeaderFont, ['Alignment' => 'center']);
        $table->addCell(8000, $styleHeaderCell)->addText(_WT('Commentaire'), $styleHeaderFont, ['Alignment' => 'center']);

        // Fill in each row
        for ($row = $threatsScale['min']; $row <= $threatsScale['max']; ++$row) {
            $table->addRow(400);

            $table->addCell(80, $styleContentCell)->addText($row, $styleContentFont, ['Alignment' => 'center']);

            // Find the appropriate comment
            $commentText = '';
            foreach ($threatsComments as $comment) {
                if ($comment['val'] == $row) {
                    $commentText = $comment['comment' . $anr->language];
                    break;
                }
            }

            $table->addCell(5000, $styleContentCell)->addText(_WT($commentText), $styleContentFont, ['Alignment' => 'left']);
        }

        $values['SCALE_THREAT'] = $this->getWordXmlFromWordObject($tableWord);
        unset($tableWord);

        // Generate vuln table
        $vulnsScale = current(current($this->scaleService->getList(1, 0, null, null, ['anr' => $anr->id, 'type' => 3])));
        $vulnsComments = $this->scaleCommentService->getList(1, 0, null, null, ['anr' => $anr->id, 'scale' => $vulnsScale['id']]);

        $tableWord = new PhpWord();
        $section = $tableWord->addSection();
        $table = $section->addTable($styleTable);

        $table->addRow(400);
        $table->addCell(80, $styleHeaderCell)->addText(_WT('Niveau'), $styleHeaderFont, ['Alignment' => 'center']);
        $table->addCell(8000, $styleHeaderCell)->addText(_WT('Commentaire'), $styleHeaderFont, ['Alignment' => 'center']);


        // Fill in each row
        for ($row = $vulnsScale['min']; $row <= $vulnsScale['max']; ++$row) {
            $table->addRow(400);

            $table->addCell(80, $styleContentCell)->addText($row, $styleContentFont, ['Alignment' => 'center']);

            // Find the appropriate comment
            $commentText = '';
            foreach ($vulnsComments as $comment) {
                if ($comment['val'] == $row) {
                    $commentText = $comment['comment' . $anr->language];
                    break;
                }
            }

            $table->addCell(5000, $styleContentCell)->addText(_WT($commentText), $styleContentFont, ['Alignment' => 'left']);
        }

        $values['SCALE_VULN'] = $this->getWordXmlFromWordObject($tableWord);
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

        $table->addRow(400);
        $table->addCell(400, $risksTableCellStyle)->addText('');
        foreach ($header as $MxV) {
            $table->addCell(400, $risksTableCellStyle)->addText($MxV, $risksTableFontStyle);
        }

        for ($row = $impactsScale['min']; $row <= $impactsScale['max']; ++$row) {
            $table->addRow(400);
            $table->addCell(400, $risksTableCellStyle)->addText($row, $risksTableFontStyle);

            foreach ($header as $MxV) {
                $value = $MxV * $row;

                if ($value <= $anr->seuil1) {
                    $style = $risksTableGreenCellStyle;
                } else if ($value <= $anr->seuil2) {
                    $style = $risksTableOrangeCellStyle;
                } else {
                    $style = $risksTableRedCellStyle;
                }

                $table->addCell(400, $style)->addText($MxV * $row, $risksTableValueFontStyle, ['align' => 'center']);
            }
        }

        $values['TABLE_RISKS'] = $this->getWordXmlFromWordObject($tableWord);
        unset($tableWord);

        // Table which represents "particular attention" threats
        $values['TABLE_THREATS'] = $this->generateThreatsTable($anr, false);

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
            if(!empty($response)){
                $table->addRow(400);
                $table->addCell(11000, $styleHeaderCell)->addText(_WT($question['label' . $anr->language]), $styleHeaderFont, ['Alignment' => 'left', 'align' => 'start']);
                $table->addRow(800);
                $table->addCell(11000, $styleContentCell)->addText(_WT($response), $styleContentFont, ['Alignment' => 'left', 'align' => 'start']);
            }
        }

        $values['TABLE_EVAL_TEND'] = $this->getWordXmlFromWordObject($tableWord);
        unset($tableWord);

        // Figure B: Full threats table
        $values['TABLE_THREATS_FULL'] = $this->generateThreatsTable($anr, true);

        // Figure C: Interviews table
        $interviews = $this->interviewService->getList(1, 0, null, null, ['anr' => $anr->id]);

        $tableWord = new PhpWord();
        $section = $tableWord->addSection();
        $table = $section->addTable($styleTable);

        $table->addRow(400);

        $table->addCell(6000, $styleHeaderCell)->addText("Date", $styleHeaderFont);
        $table->addCell(10000, $styleHeaderCell)->addText("Service / Personnes", $styleHeaderFont);
        $table->addCell(14000, $styleHeaderCell)->addText("Contenu", $styleHeaderFont);

        // Fill in each row
        foreach ($interviews as $interview) {
            $table->addRow(400);

            $table->addCell(6000, $styleContentCell)->addText(_WT($interview['date']), $styleContentFont, ['Alignment' => 'left']);
            $table->addCell(10000, $styleContentCell)->addText(_WT($interview['service']), $styleContentFont, ['Alignment' => 'left']);
            $table->addCell(14000, $styleContentCell)->addText(_WT($interview['content']), $styleContentFont, ['Alignment' => 'left']);
        }

        $values['TABLE_INTERVIEW'] = $this->getWordXmlFromWordObject($tableWord);
        unset($tableWord);

        return $values;
    }

    protected function buildContextModelingValues($anr) {
        // Models are incremental, so use values from level-1 model
        $values = $this->buildContextValidationValues($anr);

        $values['SYNTH_ACTIF'] = $this->generateWordXmlFromHtml($anr->synthAct);
        $values['IMPACTS_APPRECIATION'] = $this->generateImpactsAppreciation($anr);

        return $values;
    }

    protected function buildRiskAssessmentValues($anr) {
        // Models are incremental, so use values from level-2 model
        $values = $this->buildContextModelingValues($anr);

        $values['DISTRIB_EVAL_RISK'] = $this->generateWordXmlFromHtml($this->getRisksDistribution($anr));

        $values['GRAPH_EVAL_RISK'] = $this->generateRisksGraph($anr);

        $values['RISKS_RECO'] = $this->generateRisksPlan($anr, false);
        $values['RISKS_RECO_FULL'] = $this->generateRisksPlan($anr, true);
        $values['TABLE_AUDIT_INSTANCES'] = $this->generateTableAudit($anr);

        return $values;
    }

    protected function generateRisksGraph($anr) {
        $this->cartoRiskService->buildListScalesAndHeaders($anr->id);
        list($counters, $distrib) = $this->cartoRiskService->getCountersRisks('raw'); // raw = without target

        $maxValue = max($distrib);

        $styleTable = ['borderSize' => '0', 'borderColor' => 'FFFFFF'];
        $styleHeaderCell = ['valign' => 'center', 'bgcolor' => 'DFDFDF', 'size' => 10];
        $styleHeaderFont = ['bold' => true, 'size' => 10];
        $styleValueFont = [];
        $styleValueFont[0] = ['bold' => true, 'size' => 10, 'color' => '000000'];
        $styleValueFont[1] = ['bold' => true, 'size' => 10, 'color' => '000000'];
        $styleValueFont[2] = ['bold' => true, 'size' => 10, 'color' => 'FFFFFF'];

        $styleHeaderCellVal = [];
        $styleHeaderCellVal[0] = ['bgcolor' => 'D6F107', 'size' => 10, 'valign' => 'center'];
        $styleHeaderCellVal[1] = ['bgcolor' => 'FFBC1C', 'size' => 10, 'valign' => 'center'];
        $styleHeaderCellVal[2] = ['bgcolor' => 'FD661F', 'size' => 10, 'valign' => 'center'];

        $labels = ['Risques faibles', 'Risques moyens', 'Risques critiques'];

        $allWordXml = '';

        for ($row = 0; $row < 3; ++$row) {
            $tableWord = new PhpWord();
            $section = $tableWord->addSection();
            $table = $section->addTable($styleTable);

            $table->addRow(200);
            $table->addCell(3200, $styleHeaderCell)->addText(_WT($labels[$row]), $styleHeaderFont, ['Alignment' => 'center']);

            if ($maxValue > 0 && isset($distrib[$row])) {
                $percentage = $distrib[$row] * 100 / $maxValue;
            } else {
                $percentage = 0;
            }

            if ($percentage > 0) {
                $table->addCell(intval($percentage * 30), $styleHeaderCellVal[$row])->addText($distrib[$row], $styleValueFont[$row], ['Alignment' => 'end']);
            }

            $allWordXml .= $this->getWordXmlFromWordObject($tableWord);
            unset($tableWord);
        }

        return $allWordXml;
    }

    protected function generateTableAudit($anr) {
        $query = $this->instanceRiskTable->getRepository()->createQueryBuilder('ir');
        $result = $query->select([
            'i.id', 'i.name'.$anr->language.' as name', 'IDENTITY(i.root)', 'IDENTITY(i.object)',
            'm.id as mid', 'm.label'.$anr->language.' as mlabel',
            'v.id as vid', 'v.label'.$anr->language.' as vlabel',
            'ir.comment',
        ])->where('ir.anr = :anrid')
            ->setParameter(':anrid', $anr->id)
            ->innerJoin('ir.instance',      'i')
            ->innerJoin('ir.threat',        'm')
            ->innerJoin('ir.vulnerability', 'v')
            ->getQuery()->getResult();


        $mem_risks = [];
        foreach($result as $r) {
            if (!isset($mem_risks[$r['id']])) {
                $mem_risks[$r['id']] = [];
                $mem_risks[$r['id']]['ctx'] = $r['name'];
                $mem_risks[$r['id']]['risks'] = [];
            }

            $mem_risks[$r['id']]['risks'][] = [
                'm' => $r['mlabel'],
                'v' => $r['vlabel'],
                'comment' => $r['comment']
            ];
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

            $table->addRow(400);
            $table->addCell(3000, $styleHeaderCell)->addText(_WT('Menace'), $styleHeader2Font, ['Alignment' => 'center']);
            $table->addCell(3000, $styleHeaderCell)->addText(_WT('Vulnerabilité'), $styleHeader2Font, ['Alignment' => 'center']);
            $table->addCell(6000, $styleHeaderCell)->addText(_WT('Mesure en place'), $styleHeader2Font, ['Alignment' => 'center']);

            foreach ($mem_risks as $id_inst => $data) {
                $table->addRow(400);
                $table->addCell(12000, $cellColSpan)->addText(_WT($data['ctx']), $styleContentFont, ['Alignment' => 'left']);

                if (!empty($data['risks'])) {
                    foreach ($data['risks'] as $r) {
                        $table->addRow(400);
                        $table->addCell(3000, $styleContentCell)->addText(_WT($r['m']), $styleContentFont, ['Alignment' => 'left']);
                        $table->addCell(3000, $styleContentCell)->addText(_WT($r['v']), $styleContentFont, ['Alignment' => 'left']);
                        $table->addCell(6000, $styleContentCell)->addText(_WT($r['comment']), $styleContentFont, ['Alignment' => 'left']);
                    }
                }
            }

            return $this->getWordXmlFromWordObject($tableWord);
        } else {
            return '';
        }
    }

    protected function getRisksDistribution($anr) {
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

        $intro = sprintf("La liste des risques traités est fournie en fichier annexe. Il répertorie %d risque(s) dont :", $sum);
        return $intro . '<br/><ul>' .
            '<li>' . sprintf('%d risque(s) critique(s) à traiter en priorité', $distrib[2]) . '</li>' .
            '<li>' . sprintf('%d risque(s) moyen(s) à traiter partiellement', $distrib[1]) . '</li>' .
            '<li>' . sprintf('%d risque(s) faible(s) négligeables', $distrib[0]) . '</li></ul>';
    }

    protected function generateRisksPlan($anr, $full = false) {
        $recos = $this->recommandationService->getList(1, 0, null, null, ['anr' => $anr->id]);

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

        $table->addRow(400);
        $table->addCell(4500, $styleHeaderCell)->addText('Mesures en place', $styleHeaderFont, $alignCenter);
        $table->addCell(2000, $styleHeaderCell)->addText('Actif', $styleHeaderFont, $alignCenter);
        $table->addCell(4500, $styleHeaderCell)->addText('Recommandation', $styleHeaderFont, $alignCenter);
        $table->addCell(500, $styleHeaderCell)->addText('Imp.', $styleHeaderFont, $alignCenter);

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
                            $instanceRisk = $this->instanceRiskTable->get($risk['instanceRisk']->id);
                            if ($instanceRisk['kindOfMeasure'] == 5) continue;
                        } else if ($risk['instanceRiskOp']) {
                            $instanceRiskOp = $this->instanceRiskOpTable->get($risk['instanceRiskOp']->id);
                            if ($instanceRiskOp['kindOfMeasure'] == 5) continue;
                        }


                        $table->addRow(400);

                        if ($first) {
                            $cellfusion = $cellRowSpanStart;
                            $first = false;
                        } else {
                            $cellfusion = $cellRowSpanContinue;
                        }

                        $table->addCell(4500, $styleContentCell)->addText(_WT($risk['commentAfter']), $styleContentFont, ['Alignment' => 'left']);
                        $table->addCell(2000, $styleContentCell)->addText(_WT($risk['instance']->{'name' . $anr->language}), $styleContentFont, ['Alignment' => 'left']);

                        $contentreco = "[" . $reco['code'] . "] " . _WT($reco['description']);
                        $table->addCell(4500, $cellfusion)->addText($contentreco, $styleContentFont, ['Alignment' => 'left']);

                        switch ($reco['importance']) {
                            case 0: $contentreco = ""; break;
                            case 1: $contentreco = "o"; break;
                            case 2: $contentreco = "oo"; break;
                            case 3: $contentreco = "ooo"; break;
                        }

                        $table->addCell(800, $cellfusion)->addText(_WT($contentreco), $styleContentFontRed);
                    }
                }
            }

            if ($cpte_elem > $max_elem && !$full) {
                break;
            }
        }

        return $this->getWordXmlFromWordObject($tableWord);
    }

    protected function generateImpactsAppreciation($anr) {
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

        $table->addRow(400);
        $table->addCell(8000, $styleHeaderCell)->addText('Label', $styleHeaderFont, $alignCenter);
        $table->addCell(800, $styleHeaderCell)->addText("C", $styleHeaderFont, $alignCenter);
        $table->addCell(800, $styleHeaderCell)->addText("I", $styleHeaderFont, $alignCenter);
        $table->addCell(800, $styleHeaderCell)->addText("D", $styleHeaderFont, $alignCenter);

        foreach ($instances as $i) {
            $table->addRow(400);
            $table->addCell(8000, $styleContentCell)->addText($i['name' . $anr->language], $styleContentFont, ['Alignment' => 'left']);
            $table->addCell(800, $styleContentCell)->addText($i['c'], $styleContentFont, $alignCenter);
            $table->addCell(800, $styleContentCell)->addText($i['i'], $styleContentFont, $alignCenter);
            $table->addCell(800, $styleContentCell)->addText($i['d'], $styleContentFont, $alignCenter);
        }

        return $this->getWordXmlFromWordObject($tableWord);
    }

    protected function generateThreatsTable($anr, $fullGen = false) {
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

        $table->addRow(400);
        $table->addCell(1000, $styleHeaderCell)->addText('Code', $styleHeaderFont, array('Alignment' => 'center'));
        $table->addCell(2500, $styleHeaderCell)->addText('Menace', $styleHeaderFont, array('Alignment' => 'center'));
        $table->addCell(700, $styleHeaderCell)->addText('CID', $styleHeaderFont, array('Alignment' => 'center'));
        $table->addCell(1000, $styleHeaderCell)->addText('Tend.', $styleHeaderFont, array('Alignment' => 'center'));
        $table->addCell(1500, $styleHeaderCell)->addText('Prob.', $styleHeaderFont, array('Alignment' => 'center'));
        $table->addCell(2500, $styleHeaderCell)->addText('Commentaire', $styleHeaderFont, array('Alignment' => 'center'));

        foreach ($threats as $threat) {
            if (($threat['trend'] > 0 && $threat['trend'] != 2) || $fullGen) { // All but normal
                $table->addRow(400);
                $table->addCell(1000, $styleContentCellCenter)->addText(_WT($threat['code']), $styleContentFont, array('Alignment' => 'left'));
                $table->addCell(2500, $styleContentCell)->addText(_WT($threat['label'.$anr->language]), $styleContentFont, array('Alignment' => 'left'));

                // CID
                $cid = '';
                if ($threat['c']) $cid .= 'C';
                if ($threat['i']) $cid .= 'I';
                if ($threat['d']) $cid .= 'D';
                $table->addCell(700, $styleContentCellCenter)->addText($cid, $styleContentFont, array('Alignment' => 'center'));

                // Trend
                $trend = '';
                switch ($threat['trend']) {
                    case 1: $trend = '-'; break;
                    case 2: $trend = 'n'; break;
                    case 3: $trend = '+'; break;
                    case 4: $trend = '++'; break;
                }
                $table->addCell(1000, $styleContentCellCenter)->addText($trend, $styleContentFont, array('Alignment' => 'center'));

                // Pre-Q
                $qual = $threat['qualification'] >= 0 ? $threat['qualification'] : '';
                $table->addCell(1500, $styleContentCellCenter)->addText($qual, $styleContentFont, array('Alignment' => 'center'));
                $table->addCell(2500, $styleContentCellCenter)->addText(_WT($threat['comment']));
            }
        }

        return $this->getWordXmlFromWordObject($tableWord);
    }

    protected function getCompanyName() {
        $client = current($this->clientTable->fetchAll());
        return $client['name'];
    }

    protected function generateWordXmlFromHtml($input) {
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
        return(
            str_replace(
                ['w:val="'],
                ['w:val="1'],
                $this->getWordXmlFromWordObject($phpWord, true))
        );
    }

    protected function getWordXmlFromWordObject($phpWord, $useBody = true) {
        // Portion Copyright © Netlor SAS - 2015
        $part = new \PhpOffice\PhpWord\Writer\Word2007\Part\Document();
        $part->setParentWriter(new Word2007($phpWord));
        $docXml = $part->write();
        $matches = array();

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

function _WT($input) {
    return str_replace(['&quot;', '&amp;lt', '&amp;gt', '&amp;'], ['"', '_lt_', '_gt_', '_amp_'], htmlspecialchars(trim($input), ENT_COMPAT, 'UTF-8'));
}