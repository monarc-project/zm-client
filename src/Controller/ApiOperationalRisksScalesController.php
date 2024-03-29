<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Controller;

use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\View\Model\JsonModel;
use Monarc\Core\Exception\Exception;
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
            'id' => $this->operationalRiskScaleService->createOperationalRiskScaleType($anrId, $data),
        ]);
    }

    public function deleteList($data)
    {
        $this->operationalRiskScaleService->deleteOperationalRiskScaleTypes($data);

        return new JsonModel(['status' => 'ok']);
    }

    public function update($id, $data)
    {
        $data['anr'] = (int)$this->params()->fromRoute('anrid');

        if ($this->operationalRiskScaleService->update((int)$id, $data)) {
            return new JsonModel(['status' => 'ok']);
        }

        // Not successful
        return new JsonModel(['status' => 'ko']);
    }

    public function patchList($data)
    {
        $data['anr'] = (int)$this->params()->fromRoute('anrid');

        if (isset($data['scaleValue'], $data['scaleIndex'])) {
            $this->operationalRiskScaleService->updateValueForAllScales($data);
        }

        if (isset($data['numberOfLevelForOperationalImpact'])) {
            $numberOfLevelForOperationalImpact = (int)$data['numberOfLevelForOperationalImpact'];

            if ($numberOfLevelForOperationalImpact > 20) {
                throw new Exception('Scales level must remain below 20 ', 412);
            }

            $this->operationalRiskScaleService->updateLevelsNumberOfOperationalRiskScale($data);
        }

        if (isset($data['probabilityMin'], $data['probabilityMax'])) {
            $probabilityMin = (int)$data['probabilityMin'];
            $probabilityMax = (int)$data['probabilityMax'];

            if ($probabilityMin > 20 || $probabilityMax > 20) {
                throw new Exception('Scales level must remain below 20 ', 412);
            }
            if ($probabilityMin >= $probabilityMax) {
                throw new Exception('Minimum cannot be greater than Maximum', 412);
            }

            $this->operationalRiskScaleService->updateMinMaxForOperationalRiskProbability($data);
        }

        return new JsonModel(['status' => 'ok']);
    }
}
