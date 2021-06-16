<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Controller;

use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\View\Model\JsonModel;
use Monarc\FrontOffice\Service\OperationalRiskScaleService;

class ApiOperationalRisksScalesController extends AbstractRestfulController
{
    private OperationalRiskScaleService $operationalRiskScaleService;

    public function __construct(OperationalRiskScaleService $operationalRiskScaleService)
    {
        $this->operationalRiskScaleService = $operationalRiskScaleService;
    }

    public function getList()
    {
        $anrId = (int)$this->params()->fromRoute('anrid');

        return new JsonModel([
            'data' => $this->operationalRiskScaleService->getOperationalRiskScales($anrId),
        ]);
    }

    public function create($data)
    {
        $anrId = (int)$this->params()->fromRoute('anrid');

        return new JsonModel([
            'status' => 'ok',
            'id' => $this->operationalRiskScaleService->createOperationalRiskScale($anrId, $data),
        ]);
    }

    public function deleteList($data)
    {
        $this->operationalRiskScaleService->deleteOperationalRiskScales($data);

        return new JsonModel(['status' => 'ok']);
    }

    public function update($id, $data)
    {
        $data['anr'] = (int)$this->params()->fromRoute('anrid');

        if ($this->operationalRiskScaleService->update($id, $data)) {
            return new JsonModel(['status' => 'ok']);
        }

        return new JsonModel(['status' => 'ko']);
    }
}
