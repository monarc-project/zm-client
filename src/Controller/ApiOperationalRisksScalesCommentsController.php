<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Controller;

use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\View\Model\JsonModel;
use Monarc\FrontOffice\Service\OperationalRiskScaleCommentService;

class ApiOperationalRisksScalesCommentsController extends AbstractRestfulController
{
    private OperationalRiskScaleCommentService $operationalRiskScaleCommentService;

    public function __construct(OperationalRiskScaleCommentService $operationalRiskScaleCommentService)
    {
        $this->operationalRiskScaleCommentService = $operationalRiskScaleCommentService;
    }

    public function update($id, $data)
    {
        $data['anr'] = (int)$this->params()->fromRoute('anrid');

        if ($this->operationalRiskScaleCommentService->update($id, $data)) {
            return new JsonModel(['status' => 'ok']);
        }

        return new JsonModel(['status' => 'ko']);
    }
}
