<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\Core\Controller\AbstractController;
use Zend\View\Model\JsonModel;

/**
 * Api Anr Deliverable Controller
 *
 * Class ApiAnrDeliverableController
 * @package Monarc\FrontOffice\Controller
 */
class ApiAnrDeliverableController extends AbstractController
{
    protected $name = 'deliverable';

    /**
     * @inheritdoc
     */
    public function create($data)
    {
        $anrId = (int)$this->params()->fromRoute('anrid');
        if (empty($anrId)) {
            throw new \Monarc\Core\Exception\Exception('Anr id missing', 412);
        }
        $data['anr'] = $anrId;

        $typeDoc = $data['typedoc'];
        if (empty($typeDoc)) {
            throw new \Monarc\Core\Exception\Exception('Document type missing', 412);
        }

        $params = [
            'txt' => [
                'VERSION' => htmlspecialchars($data['version']),
                'STATE' => $data['status'] == 0 ? 'Draft' : 'Final',
                'CLASSIFICATION' => htmlspecialchars($data['classification']),
                'DOCUMENT' => htmlspecialchars($data['docname']),
                'DATE' => date('d/m/Y'),
                'CLIENT' => htmlspecialchars($data['managers']),
                'SMILE' => htmlspecialchars($data['consultants']),
            ],
            'img' => [],
            'html' => [
                'SUMMARY_EVAL_RISK' => isset($data['summaryEvalRisk']) ? $data['summaryEvalRisk'] : '',
            ],
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
            throw new \Monarc\Core\Exception\Exception("Generated file not found: " . $filePath);
        }
    }

    /**
     * @inheritdoc
     */
    public function get($id)
    {
        $anrId = (int)$this->params()->fromRoute('anrid');
        if (empty($anrId)) {
            throw new \Monarc\Core\Exception\Exception('Anr id missing', 412);
        }

        $result = [
            'delivery' => $this->getService()->getLastDeliveries($anrId, $id),
        ];
        return new JsonModel($result);
    }

    /**
     * @inheritdoc
     */
    public function getList()
    {
        $anrId = (int)$this->params()->fromRoute('anrid');
        if (empty($anrId)) {
            throw new \Monarc\Core\Exception\Exception('Anr id missing', 412);
        }

        $result = [
            'models' => $this->getService()->getDeliveryModels(),
            'delivery' => $this->getService()->getLastDeliveries($anrId),
        ];
        return new JsonModel($result);
    }

    /**
     * @inheritdoc
     */
    public function delete($id)
    {
        return $this->methodNotAllowed();
    }

    /**
     * @inheritdoc
     */
    public function deleteList($data)
    {
        return $this->methodNotAllowed();
    }

    /**
     * @inheritdoc
     */
    public function update($id, $data)
    {
        return $this->methodNotAllowed();
    }

    /**
     * @inheritdoc
     */
    public function patch($id, $data)
    {
        return $this->methodNotAllowed();
    }
}
