<?php

namespace MonarcFO\Controller;

use MonarcCore\Model\Entity\AbstractEntity;
use MonarcCore\Service\DeliveriesModelsService;
use Zend\View\Model\JsonModel;

/**
 * Api Anr Deliverable Controller
 *
 * Class ApiAnrDeliverableController
 * @package MonarcFO\Controller
 */
class ApiAnrDeliverableController extends \MonarcCore\Controller\AbstractController
{
    protected $name = 'deliverable';

    public function get($id)
    {
        return $this->methodNotAllowed();
    }
    public function create($data)
    {
        $anrId = (int) $this->params()->fromRoute('anrid');
        if(empty($anrId)){
            throw new \Exception('Anr id missing', 412);
        }
        $data['anr'] = $anrId;

        $modelId = $data['model'];
        if(empty($anrId)){
            throw new \Exception('Model id missing', 412);
        }

        $params = [
            'VERSION' => $data['version'],
            'STATE' => $data['status'] == 0 ? 'Brouillon' : 'Final',
            'CLASSIFICATION' => $data['classification'],
            'DOCUMENT' => $data['docname'],
            'DATE' => date('d/m/Y, H:i'),
            'CLIENT' => $data['managers'],
            'SMILE' => $data['consultants'],
            'SUMMARY_EVAL_RISK' => isset($data['summaryEvalRisk']) ? $data['summaryEvalRisk'] : '',
        ];

        // Generate the DOCX file
        $filePath = $this->getService()->generateDeliverableWithValues($anrId, $modelId, $params, $data);

        if (file_exists($filePath)) {
            $response = $this->getResponse();
            $response->setContent(file_get_contents($filePath));

            unlink($filePath);

            $headers = $response->getHeaders();
            $headers->clearHeaders()
                ->addHeaderLine('Content-Type', 'text/plain; charset=utf-8')
                ->addHeaderLine('Content-Disposition', 'attachment; filename="deliverable.docx"');

            return $this->response;
        } else {
            throw new \Exception("Generated file not found: " . $filePath);
        }
    }

    public function getList()
    {
        $anrId = (int) $this->params()->fromRoute('anrid');
        if(empty($anrId)){
            throw new \Exception('Anr id missing', 412);
        }

        $result = [
            'models' => $this->getService()->getDeliveryModels(),
            'delivery' => $this->getService()->getLastDeliveries($anrId),
        ];
        return new JsonModel($result);
    }

    /**
     * Delete
     *
     * @param mixed $id
     * @return JsonModel
     */
    public function delete($id)
    {
        return $this->methodNotAllowed();
    }

    /**
     * Delete list
     *
     * @param mixed $data
     * @return JsonModel
     */
    public function deleteList($data)
    {
        return $this->methodNotAllowed();
    }

    /**
     * Update
     *
     * @param mixed $id
     * @param mixed $data
     * @return JsonModel
     */
    public function update($id, $data)
    {
        return $this->methodNotAllowed();
    }

    /**
     * Patch
     *
     * @param mixed $id
     * @param mixed $data
     * @return JsonModel
     */
    public function patch($id, $data)
    {
        return $this->methodNotAllowed();
    }


}
