<?php
namespace MonarcFO\Service;
use MonarcCore\Service\AbstractServiceFactory;
use MonarcCore\Service\DeliveriesModelsService;
use MonarcCore\Service\QuestionChoiceService;
use MonarcCore\Service\QuestionService;
use MonarcFO\Model\Table\AnrTable;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\TemplateProcessor;
use PhpOffice\PhpWord\Writer\Word2007;

/**
 * Anr Deliverable Service
 *
 * Class AnrAssetService
 * @package MonarcFO\Service
 */
class DeliverableGenerationService extends AbstractServiceFactory
{
    use \MonarcCore\Model\GetAndSet;

    /** @var  DeliveriesModelsService */
    protected $deliveryModelService;
    /** @var  AnrTable */
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

    public function generateDeliverableWithValues($anrId, $modelId, $values) {
        // Find the model to use
        $model = $this->deliveryModelService->getEntity($modelId);
        if (!$model) {
            throw new \Exception("Model `id` not found");
        }

        // Load the ANR
        $anr = $this->anrTable->getEntity($anrId);
        if (!$anr) {
            throw new \Exception("Anr `id` not found");
        }

        $values = array_merge($values, $this->buildValues($anr, $model['category']));
        return $this->generateDeliverableWithValuesAndModel($model['path' . $anr->language], $values);
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
            'COMPANY' => $this->getCompanyName($anr),
            'CONTEXT_ANA_RISK' => $this->generateWordXmlFromHtml($anr->contextAnaRisk),
            'CONTEXT_GEST_RISK' => $this->generateWordXmlFromHtml($anr->contextGestRisk),
            'SYNTH_EVAL_THREAT' => $this->generateWordXmlFromHtml($anr->synthThreat),
        ];

        // Generate impacts table
        $impactsScale = current(current($this->scaleService->getList(1, 0, null, null, ['anr' => $anr->id, 'type' => 1])));
        $impactsTypes = $this->scaleTypeService->getList(1, 0, null, null, ['anr' => $anr->id]);
        $impactsComments = $this->scaleCommentService->getList(1, 0, null, null, ['anr' => $anr->id, 'scale' => $impactsScale['id']]);

        $tableWord = new PhpWord();
        $section = $tableWord->addSection();
        $table = $section->addTable();

        $styleHeaderCell = array('valign' => 'center', 'BorderSize' => 6, 'BorderColor' => '999999');
        $styleHeaderFont = array('bold' => true);

        $table->addRow(400);
        $table->addCell(2000, $styleHeaderCell)->addText(' ', $styleHeaderFont);
        foreach ($impactsTypes as $impactType) {
            $table->addCell(2000, $styleHeaderCell)->addText($impactType['label' . $anr->language], $styleHeaderFont);
        }

        // Fill in each row
        for ($row = $impactsScale['min']; $row <= $impactsScale['max']; ++$row) {
            $table->addRow(400);

            $table->addCell(2000, $styleHeaderCell)->addText($row);

            foreach ($impactsTypes as $impactType) {
                // Find the appropriate comment
                $commentText = '';
                foreach ($impactsComments as $comment) {
                    if ($comment['scaleImpactType']->id == $impactType['id'] && $comment['val'] == $row) {
                        $commentText = $comment['comment' . $anr->language];
                        break;
                    }
                }

                $table->addCell(2000, $styleHeaderCell)->addText($commentText);
            }
        }

        $values['SCALE_IMPACT'] = $this->getWordXmlFromWordObject($tableWord);
        unset($tableWord);

        // Generate threat table
        $threatsScale = current(current($this->scaleService->getList(1, 0, null, null, ['anr' => $anr->id, 'type' => 2])));
        $threatsComments = $this->scaleCommentService->getList(1, 0, null, null, ['anr' => $anr->id, 'scale' => $threatsScale['id']]);

        $tableWord = new PhpWord();
        $section = $tableWord->addSection();
        $table = $section->addTable(['align' => 'center']);

        // Fill in each row
        for ($row = $threatsScale['min']; $row <= $threatsScale['max']; ++$row) {
            $table->addRow(400);

            $table->addCell(500, $styleHeaderCell)->addText($row);

            // Find the appropriate comment
            $commentText = '';
            foreach ($threatsComments as $comment) {
                if ($comment['val'] == $row) {
                    $commentText = $comment['comment' . $anr->language];
                    break;
                }
            }

            $table->addCell(5000, $styleHeaderCell)->addText($commentText);
        }

        $values['SCALE_THREAT'] = $this->getWordXmlFromWordObject($tableWord);
        unset($tableWord);

        // Generate vuln table
        $vulnsScale = current(current($this->scaleService->getList(1, 0, null, null, ['anr' => $anr->id, 'type' => 3])));
        $vulnsComments = $this->scaleCommentService->getList(1, 0, null, null, ['anr' => $anr->id, 'scale' => $vulnsScale['id']]);

        $tableWord = new PhpWord();
        $section = $tableWord->addSection();
        $table = $section->addTable(['align' => 'center']);

        // Fill in each row
        for ($row = $vulnsScale['min']; $row <= $vulnsScale['max']; ++$row) {
            $table->addRow(400);

            $table->addCell(500, $styleHeaderCell)->addText($row);

            // Find the appropriate comment
            $commentText = '';
            foreach ($vulnsComments as $comment) {
                if ($comment['val'] == $row) {
                    $commentText = $comment['comment' . $anr->language];
                    break;
                }
            }

            $table->addCell(5000, $styleHeaderCell)->addText($commentText);
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
        $values['TABLE_THREATS'] = '';

        // Figure A: Trends (Q/A)
        $questions = $this->questionService->getList(1, 0, null, null, ['anr' => $anr->id]);
        $questionsChoices = $this->questionChoiceService->getList(1, 0, null, null, ['anr' => $anr->id]);
        $tableWord = new PhpWord();
        $section = $tableWord->addSection();
        $table = $section->addTable(['align' => 'center']);

        // Fill in each row
        foreach ($questions as $question) {
            $table->addRow(400);

            $table->addCell(3000, $styleHeaderCell)->addText($question['label' . $anr->language]);

            if ($question['type'] == 1) {
                // Simple text
                $response = $question['response'];
            } else {
                // Choice, either simple or multiple
                if ($question['multichoice']) {
                    $responseIds = json_decode($question['response']);
                    $responses = [];

                    foreach ($questionsChoices as $choice) {
                        if (array_search($choice['id'], $responseIds) !== false) {
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

            $table->addCell(5000, $styleHeaderCell)->addText($response, ['alignment' => 'start'], ['align' => 'start']);
        }

        $values['TABLE_EVAL_TEND'] = $this->getWordXmlFromWordObject($tableWord);
        unset($tableWord);

        // Figure B: Full threats table
        $values['TABLE_THREATS_FULL'] = '';

        // Figure C: Interviews table
        $interviews = $this->interviewService->getList(1, 0, null, null, ['anr' => $anr->id]);

        $tableWord = new PhpWord();
        $section = $tableWord->addSection();
        $table = $section->addTable(['align' => 'center']);

        $table->addRow(400);

        $table->addCell(2000, $styleHeaderCell)->addText("Date", $styleHeaderFont);
        $table->addCell(2000, $styleHeaderCell)->addText("Service / Personnes", $styleHeaderFont);
        $table->addCell(2000, $styleHeaderCell)->addText("Contenu", $styleHeaderFont);

        // Fill in each row
        foreach ($interviews as $interview) {
            $table->addRow(400);

            $table->addCell(3000, $styleHeaderCell)->addText($interview['date']);
            $table->addCell(5000, $styleHeaderCell)->addText($interview['service']);
            $table->addCell(7000, $styleHeaderCell)->addText($interview['content']);
        }

        $values['TABLE_INTERVIEW'] = $this->getWordXmlFromWordObject($tableWord);
        unset($tableWord);

        return $values;
    }

    protected function buildContextModelingValues($anr) {
        // Models are incremental, so use values from level-1 model
        $values = $this->buildContextValidationValues($anr);
        $values['SYNTH_ACTIF'] = $this->generateWordXmlFromHtml($anr->synthAct);
        // IMPACTS_APPRECIATION


        return $values;
    }

    protected function buildRiskAssessmentValues($anr) {
        // Models are incremental, so use values from level-2 model
        $values = $this->buildContextModelingValues($anr);

        // SUMMARY_EVAL_RISK

        // DISTRIB_EVAL_RISK

        // GRAPH_EVAL_RISK

        // RISKS_RECO_FULL

        // TABLE_AUDIT_INSTANCES

        return $values;
    }

    protected function getCompanyName($anr) {
        return 'N/A';
    }

    protected function generateWordXmlFromHtml($input) {
        // Portion Copyright © Netlor SAS - 2015
        // Process trix caveats
        $input = str_replace(
            ['<br>', '<div>', '</div>', '<blockquote>', '</blockquote>'],
            ['</p><p>', '<p>', '</p>', '<blockquote><p>', '</p></blockquote>'],
            $input);

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

        if ($useBody) {
            $regex = '/<w:body>(.*)<w:sectPr>/is';
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
