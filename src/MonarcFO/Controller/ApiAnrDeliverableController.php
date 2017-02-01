<?php
/**
 * @link      https://github.com/CASES-LU for the canonical source repository
 * @copyright Copyright (c) Cases is a registered trademark of SECURITYMADEIN.LU
 * @license   MyCases is licensed under the GNU Affero GPL v3 - See license.txt for more information
 */

namespace MonarcFO\Controller;

use MonarcCore\Controller\AbstractController;
use Zend\View\Model\JsonModel;

/**
 * Api Anr Deliverable Controller
 *
 * Class ApiAnrDeliverableController
 * @package MonarcFO\Controller
 */
class ApiAnrDeliverableController extends AbstractController
{
    protected $name = 'deliverable';

    /**
     * Create
     *
     * @param mixed $data
     * @return \Zend\Stdlib\ResponseInterface
     * @throws \Exception
     */
    public function create($data)
    {
        $anrId = (int)$this->params()->fromRoute('anrid');
        if (empty($anrId)) {
            throw new \Exception('Anr id missing', 412);
        }
        $data['anr'] = $anrId;

        $typeDoc = $data['typedoc'];
        if (empty($typeDoc)) {
            throw new \Exception('Document type missing', 412);
        }

        $params = [
            'txt' => [
                'VERSION' => $data['version'],
                'STATE' => $data['status'] == 0 ? 'Brouillon' : 'Final',
                'CLASSIFICATION' => $data['classification'],
                'DOCUMENT' => $data['docname'],
                'DATE' => date('d/m/Y, H:i'),
                'CLIENT' => $data['managers'],
                'SMILE' => $data['consultants'],
                'SUMMARY_EVAL_RISK' => isset($data['summaryEvalRisk']) ? $data['summaryEvalRisk'] : '',
            ],
            'img' => [],
        ];

        // Generate the DOCX file
        $filePath = $this->getService()->generateDeliverableWithValues($anrId, $typeDoc, $params, $data);

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
     * Get
     *
     * @param mixed $id
     * @return JsonModel
     * @throws \Exception
     */
    public function get($id)
    {
        $anrId = (int)$this->params()->fromRoute('anrid');
        if (empty($anrId)) {
            throw new \Exception('Anr id missing', 412);
        }

        $result = [
            'delivery' => $this->getService()->getLastDeliveries($anrId, $id),
        ];
        return new JsonModel($result);
    }

    /**
     * Get List
     *
     * @return JsonModel
     * @throws \Exception
     */
    public function getList()
    {
        $anrId = (int)$this->params()->fromRoute('anrid');
        if (empty($anrId)) {
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