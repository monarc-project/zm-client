<?php
namespace MonarcFO\Service;
use MonarcCore\Service\AbstractServiceFactory;
use MonarcCore\Service\DeliveriesModelsService;
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
        }
    }

    protected function buildContextValidationValues($anr) {
        // Values read from database
        $values = [
            'COMPANY' => 'N/A',
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

        $styleHeaderCell = array('valign' => 'center');
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
        $table = $section->addTable();

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
        $table = $section->addTable();

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
        $values['TABLE_RISKS'] = '';

        // Table which represents "particular attention" threats
        $values['TABLE_THREATS'] = '';

        // Figure A: Trends (Q/A)
        $values['TABLE_EVAL_TEND'] = '';

        // Figure B: Full threats table
        $values['TABLE_THREATS_FULL'] = '';

        // Figure C: Interviews table
        $values['TABLE_INTERVIEW'] = '';

        return $values;
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
