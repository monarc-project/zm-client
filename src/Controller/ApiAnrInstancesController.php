<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\Core\Controller\Handler\AbstractRestfulControllerRequestHandler;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\Core\Validator\InputValidator\Instance\CreateInstanceDataInputValidator;
use Monarc\Core\Validator\InputValidator\Instance\PatchInstanceDataInputValidator;
use Monarc\Core\Validator\InputValidator\Instance\UpdateInstanceDataInputValidator;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Export\Controller\Traits\ExportResponseControllerTrait;
use Monarc\FrontOffice\Service\AnrInstanceRiskOpService;
use Monarc\FrontOffice\Service\AnrInstanceRiskService;
use Monarc\FrontOffice\Service\AnrInstanceService;

class ApiAnrInstancesController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;
    use ExportResponseControllerTrait;

    public function __construct(
        private AnrInstanceService $anrInstanceService,
        private AnrInstanceRiskService $anrInstanceRiskService,
        private AnrInstanceRiskOpService $anrInstanceRiskOpService,
        private CreateInstanceDataInputValidator $createInstanceDataInputValidator,
        private UpdateInstanceDataInputValidator $updateInstanceDataInputValidator,
        private PatchInstanceDataInputValidator $patchInstanceDataInputValidator
    ) {
    }

    public function getList()
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        $instancesData = $this->anrInstanceService->getInstancesData($anr);

        return $this->getPreparedJsonResponse([
            'instances' => $instancesData
        ]);
    }

    public function get($id)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        if ($this->params()->fromQuery('csv', false)) {
            return $this->prepareCsvExportResponse(
                $this->anrInstanceRiskOpService->getOperationalRisksInCsv($anr, (int)$id, $this->parseParams())
            );
        }

        if ($this->params()->fromQuery('csvInfoInst', false)) {
            return $this->prepareCsvExportResponse(
                $this->anrInstanceRiskService->getInstanceRisksInCsv($anr, (int)$id, $this->parseParams())
            );
        }

        $instanceData = $this->anrInstanceService->getInstanceData($anr, (int)$id);

        return $this->getPreparedJsonResponse($instanceData);
    }

    /**
     * Instantiation of an object to the analysis.
     *
     * @param array $data
     */
    public function create($data)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        $this->validatePostParams($this->createInstanceDataInputValidator, $data);

        $instance = $this->anrInstanceService
            ->instantiateObjectToAnr($anr, $this->createInstanceDataInputValidator->getValidData(), true);

        return $this->getSuccessfulJsonResponse(['id' => $instance->getId()]);
    }

    /**
     * Is called when instances consequences are set (edit impact).
     *
     * @param array $data
     */
    public function update($id, $data)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        $this->validatePostParams($this->updateInstanceDataInputValidator, $data);

        $this->anrInstanceService
            ->updateInstance($anr, (int)$id, $this->updateInstanceDataInputValidator->getValidData());

        return $this->getSuccessfulJsonResponse();
    }

    /**
     * Is called when we move (drag-n-drop) instance inside of analysis.
     */
    public function patch($id, $data)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        $this->validatePostParams($this->patchInstanceDataInputValidator, $data);

        $this->anrInstanceService
            ->patchInstance($anr, (int)$id, $this->patchInstanceDataInputValidator->getValidData());

        return $this->getSuccessfulJsonResponse();
    }

    public function delete($id)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        $this->anrInstanceService->delete($anr, (int)$id);

        return $this->getSuccessfulJsonResponse();
    }

    private function parseParams(): array
    {
        $params = $this->params();

        return [
            'keywords' => trim($params->fromQuery('keywords', '')),
            'kindOfMeasure' => $params->fromQuery('kindOfMeasure'),
            'order' => $params->fromQuery('order', 'maxRisk'),
            'order_direction' => $params->fromQuery('order_direction', 'desc'),
            'thresholds' => $params->fromQuery('thresholds'),
            'page' => (int)$params->fromQuery('page', 1),
            'limit' => (int)$params->fromQuery('limit', 0),
        ];
    }
}
