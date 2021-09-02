<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\Core\Exception\Exception;
use Monarc\FrontOffice\Service\DeliverableGenerationService;
use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\View\Model\JsonModel;

/**
 * Api Anr Deliverable Controller
 *
 * Class ApiAnrDeliverableController
 * @package Monarc\FrontOffice\Controller
 */
class ApiAnrDeliverableController extends AbstractRestfulController
{
    /** @var DeliverableGenerationService */
    private $deliverableGenerationService;

    public function __construct(DeliverableGenerationService $deliverableGenerationService)
    {
        $this->deliverableGenerationService = $deliverableGenerationService;
    }

    public function create($data)
    {
        $anrId = (int)$this->params()->fromRoute('anrid');
        $data['anr'] = $anrId;

        $typeDoc = $data['typedoc'];
        if (empty($typeDoc)) {
            throw new Exception('Document type missing', 412);
        }

        $params = [
            'txt' => [
                'VERSION' => htmlspecialchars($data['version']),
                'STATE' => $data['status'] == 0 ? 'Draft' : 'Final',
                'CLASSIFICATION' => htmlspecialchars($data['classification']),
                'COMPANY' => htmlspecialchars($this->deliverableGenerationService->getCompanyName()),
                'DOCUMENT' => htmlspecialchars($data['docname']),
                'DATE' => date('d/m/Y'),
                'CLIENT' => htmlspecialchars($data['managers']),
                'SMILE' => htmlspecialchars($data['consultants']),
                'SUMMARY_EVAL_RISK' => $data['summaryEvalRisk'] ?? '',
            ],
        ];

        // Generate the DOCX file
        $filePath = $this->deliverableGenerationService
            ->generateDeliverableWithValues($anrId, $typeDoc, $params, $data);

        if (file_exists($filePath)) {
            $response = $this->getResponse();
            $response->setContent(file_get_contents($filePath));

            unlink($filePath);

            $headers = $response->getHeaders();
            $headers->clearHeaders()
                ->addHeaderLine('Content-Type', 'text/plain; charset=utf-8')
                ->addHeaderLine('Content-Disposition', 'attachment; filename="deliverable.docx"');

            return $this->response;
        }

        throw new Exception('Generated file not found: ' . $filePath);
    }

    public function get($id)
    {
        return new JsonModel([
            'delivery' => $this->deliverableGenerationService->getLastDeliveries(
                (int)$this->params()->fromRoute('anrid'),
                $id
            ),
        ]);
    }

    /**
     * @inheritdoc
     */
    public function getList()
    {
        return new JsonModel([
            'models' => $this->deliverableGenerationService->getDeliveryModels(),
            'delivery' => $this->deliverableGenerationService->getLastDeliveries(
                (int)$this->params()->fromRoute('anrid')
            ),
        ]);
    }
}
