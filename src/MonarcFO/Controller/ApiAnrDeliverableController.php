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
        return $this->methodNotAllowed();
    }

    public function getList()
    {
        $modelId = $this->params()->fromQuery('model');

        $params = [
            'VERSION' => $this->params()->fromQuery('version'),
            'STATE' => $this->params()->fromQuery('status'),
            'CLASSIFICATION' => $this->params()->fromQuery('classification'),
            'DOCUMENT' => $this->params()->fromQuery('documentName'),
            'DATE' => date('d/m/Y, H:i'),
            'CLIENT' => $this->params()->fromQuery('clientManager'),
            'SMILE' => $this->params()->fromQuery('securityConsultant')
        ];

        // Generate the DOCX file
        $filePath = $this->getService()->generateDeliverableWithValues($modelId, $params);

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
