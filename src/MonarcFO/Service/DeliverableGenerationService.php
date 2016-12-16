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

        $values = array_merge($values, $this->buildValues($anrId, $model['category']));

        if (is_null($model)) {
            throw new \Exception("Model `id` not found");
        } else {
            // TODO: Handle language (path1 => pathX where X is the ANR's language)
            return $this->generateDeliverableWithValuesAndModel($model['path1'], $values);
        }
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

    protected function buildValues($anrId, $modelCategory) {
        switch ($modelCategory) {
            case 1: return $this->buildContextValidationValues($anrId);
        }
    }

    protected function buildContextValidationValues($anrId) {
        $anr = $this->anrTable->getEntity($anrId);
        if (!$anr) {
            throw new \Exception("Anr `id` not found");
        }

        // Values read from database
        $values = [
            'COMPANY' => 'N/A',
            'CONTEXT_ANA_RISK' => $this->generateWordXmlFromHtml($anr->contextAnaRisk),
            'CONTEXT_GEST_RISK' => $this->generateWordXmlFromHtml($anr->contextGestRisk),
            'SYNTH_EVAL_THREAT' => $this->generateWordXmlFromHtml($anr->synthThreat),
        ];

        // Generate impacts table
        $values['SCALE_IMPACT'] = '';

        // Generate threat table
        $values['SCALE_THREAT'] = '';

        // Generate vuln table
        $values['SCALE_VULN'] = '';

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
