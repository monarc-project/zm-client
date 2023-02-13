<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2023 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Laminas\Http\Response;
use Monarc\Core\Controller\Handler\AbstractRestfulControllerRequestHandler;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\FrontOffice\Model\Entity\Anr;
use Monarc\FrontOffice\Service\AnrInstanceRiskOpService;
use Monarc\FrontOffice\Service\AnrInstanceRiskService;
use Monarc\FrontOffice\Service\AnrInstanceService;

class ApiAnrInstancesController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;

    private AnrInstanceService $anrInstanceService;

    private AnrInstanceRiskService $anrInstanceRiskService;

    private AnrInstanceRiskOpService $anrInstanceRiskOpService;

    public function __construct(
        AnrInstanceService $anrInstanceService,
        AnrInstanceRiskService $anrInstanceRiskService,
        AnrInstanceRiskOpService $anrInstanceRiskOpService
    ) {
        $this->anrInstanceService = $anrInstanceService;
        $this->anrInstanceRiskService = $anrInstanceRiskService;
        $this->anrInstanceRiskOpService = $anrInstanceRiskOpService;
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

        $params = $this->parseParams();

        if ($this->params()->fromQuery('csv', false)) {
            return $this->setCsvResponse(
                $this->anrInstanceRiskOpService->getOperationalRisksInCsv($anr, $id, $params)
            );
        }

        if ($this->params()->fromQuery('csvInfoInst', false)) {
            return $this->setCsvResponse($this->anrInstanceRiskService->getInstanceRisksInCsv($anr, $id, $params));
        }

        $instanceData = $this->anrInstanceService->getInstanceData($anr, $id);

        return $this->getPreparedJsonResponse($instanceData);
    }

    public function update($id, $data)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        $this->anrInstanceService->updateInstance($anr, $id, $data);

        return $this->getSuccessfulJsonResponse();
    }

    /**
     * Is called when we move (drag-n-drop) instance inside of analysis.
     */
    public function patch($id, $data)
    {
        // $data payload anrId (not needed), instId, parent, position
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        $this->anrInstanceService->patchInstance($anr, $id, $data);

        return $this->getSuccessfulJsonResponse();
    }

    public function create($data)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        // TODO: move to a validator and use getValidData.
        $required = ['object', 'parent', 'position'];

        $instance = $this->anrInstanceService->instantiateObjectToAnr($anr, $data, true);

        return $this->getSuccessfulJsonResponse([
            'id' => $instance->getId(),
        ]);
    }

    /**
     * TODO: replace it with a Filter
     * Helper function to parse query parameters
     * @return array The sorted parameters
     */
    private function parseParams(): array
    {
        return [
            'keywords' => trim($this->params()->fromQuery('keywords', '')),
            'kindOfMeasure' => $this->params()->fromQuery('kindOfMeasure'),
            'order' => $this->params()->fromQuery('order', 'maxRisk'),
            'order_direction' => $this->params()->fromQuery('order_direction', 'desc'),
            'thresholds' => $this->params()->fromQuery('thresholds'),
            'page' => $this->params()->fromQuery('page', 1),
            'limit' => $this->params()->fromQuery('limit', 50)
        ];
    }

    private function setCsvResponse(string $content): Response
    {
        $response = $this->getResponse();
        $response->getHeaders()->addHeaderLine('Content-Type', 'text/csv; charset=utf-8');
        $response->setContent($content);

        return $response;
    }
}
