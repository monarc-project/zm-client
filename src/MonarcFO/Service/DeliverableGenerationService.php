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
    /** @var AnrThreatService */
    protected $threatService;
    /** @var AnrInstanceService */
    protected $instanceService;
    /** @var AnrRecommandationService */
    protected $recommandationService;
    /** @var AnrRecommandationRiskService */
    protected $recommandationRiskService;

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
            $table->addCell(2000, $styleHeaderCell)->addText(_WT($impactType['label' . $anr->language]), $styleHeaderFont);
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

                $table->addCell(2000, $styleHeaderCell)->addText(_WT($commentText));
            }
        }

        $values['SCALE_IMPACT'] = $this->getWordXmlFromWordObject($tableWord);
        unset($tableWord);

        // Generate threat scale table
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

            $table->addCell(5000, $styleHeaderCell)->addText(_WT($commentText));
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

            $table->addCell(5000, $styleHeaderCell)->addText(_WT($commentText));
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

        // Figure A: Trends (Q/A)
        $questions = $this->questionService->getList(1, 0, null, null, ['anr' => $anr->id]);
        $questionsChoices = $this->questionChoiceService->getList(1, 0, null, null, ['anr' => $anr->id]);
        $tableWord = new PhpWord();
        $section = $tableWord->addSection();
        $table = $section->addTable(['align' => 'center']);

        // Fill in each row
        foreach ($questions as $question) {
            $table->addRow(400);

            $table->addCell(3000, $styleHeaderCell)->addText(_WT($question['label' . $anr->language]));

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

            $table->addCell(5000, $styleHeaderCell)->addText(_WT($response), ['alignment' => 'start'], ['align' => 'start']);
        }

        $values['TABLE_EVAL_TEND'] = $this->getWordXmlFromWordObject($tableWord);
        unset($tableWord);

        // Figure B: Full threats table
        $values['TABLE_THREATS_FULL'] = $this->generateThreatsTable($anr, true);

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

            $table->addCell(3000, $styleHeaderCell)->addText(_WT($interview['date']));
            $table->addCell(5000, $styleHeaderCell)->addText(_WT($interview['service']));
            $table->addCell(7000, $styleHeaderCell)->addText(_WT($interview['content']));
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

        // Champ HTML sur le dernier livrable
        // $values['SUMMARY_EVAL_RISK'] = $this->generateWordXmlFromHtml($summaryEvalRisk);

        // $values['DISTRIB_EVAL_RISK'] = $this->generateWordXmlFromHtml($this->getRisksDistribution());

        // GRAPH_EVAL_RISK

        $values['RISKS_RECO_FULL'] = $this->generateRisksPlan($anr, true);

        // TABLE_AUDIT_INSTANCES

        return $values;
    }

    protected function getRisksDistribution() {
        // getCounterRisks
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
        $table->addCell(4500, $styleHeaderCell)->addText('Mesures prises', $styleHeaderFont, $alignCenter);
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
        $styleTable = array('borderSize' => 1, 'borderColor' => 'ABABAB');
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

function _WT($input) {
    return str_replace(['&quot;', '&amp;lt', '&amp;gt', '&amp;'], ['"', '_lt_', '_gt_', '_amp_'], htmlspecialchars(trim($input), ENT_COMPAT, 'UTF-8'));
}