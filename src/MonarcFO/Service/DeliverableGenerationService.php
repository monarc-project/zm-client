<?php
namespace MonarcFO\Service;
use MonarcCore\Service\AbstractServiceFactory;
use MonarcCore\Service\DeliveriesModelsService;
use PhpOffice\PhpWord\TemplateProcessor;

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

    public function generateDeliverableWithValues($modelId, $values) {
        // Find the model to use
        $model = $this->deliveryModelService->getEntity($modelId);

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
}
