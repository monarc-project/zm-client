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
use Monarc\Core\Model\Entity\Anr;
use Monarc\FrontOffice\Service\AnrInstanceRiskOpService;

class ApiAnrRisksOpController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;

    private AnrInstanceRiskOpService $anrInstanceRiskOpService;

    public function __construct(AnrInstanceRiskOpService $anrInstanceRiskOpService)
    {
        $this->anrInstanceRiskOpService = $anrInstanceRiskOpService;
    }

    /**
     * @param int $id Instance ID.
     */
    public function get($id)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');
        $params = $this->getFilterParams();

        if ($this->params()->fromQuery('csv', false)) {
            /** @var Response $response */
            $response = $this->getResponse();
            $response->getHeaders()->addHeaderLine('Content-Type', 'text/csv; charset=utf-8');
            $response->setContent(
                $this->anrInstanceRiskOpService->getOperationalRisksInCsv($anr, $id, $params)
            );

            return $response;
        }

        $risks = $this->anrInstanceRiskOpService->getOperationalRisks($anr, $id, $params);

        return $this->getPreparedJsonResponse([
            'count' => \count($risks),
            'oprisks' => $params['limit'] > 0
                ? \array_slice($risks, ($params['page'] - 1) * $params['limit'], $params['limit'])
                : $risks,
        ]);
    }

    public function getList()
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');
        $params = $this->getFilterParams();

        if ($this->params()->fromQuery('csv', false)) {
            /** @var Response $response */
            $response = $this->getResponse();
            $response->getHeaders()->addHeaderLine('Content-Type', 'text/csv; charset=utf-8');
            $response->setContent(
                $this->anrInstanceRiskOpService->getOperationalRisksInCsv($anr, null, $params)
            );

            return $response;
        }

        $risks = $this->anrInstanceRiskOpService->getOperationalRisks($anr, null, $params);

        return $this->getPreparedJsonResponse([
            'count' => \count($risks),
            'oprisks' => $params['limit'] > 0
                ? \array_slice($risks, ($params['page'] - 1) * $params['limit'], $params['limit'])
                : $risks,
        ]);
    }

    public function create($data)
    {
        return $this->getSuccessfulJsonResponse([
            'id' => $this->anrInstanceRiskOpService->createSpecificRiskOp($data),
        ]);
    }

    public function delete($id)
    {
        $this->anrInstanceRiskOpService->delete((int)$id);

        return $this->getSuccessfulJsonResponse();
    }

    protected function getFilterParams(): array
    {
        $params = $this->params();

        return [
            'keywords' => $params->fromQuery('keywords'),
            'kindOfMeasure' => $params->fromQuery('kindOfMeasure'),
            'order' => $params->fromQuery('order', 'maxRisk'),
            'order_direction' => $params->fromQuery('order_direction', 'desc'),
            'thresholds' => $params->fromQuery('thresholds'),
            'page' => (int)$params->fromQuery('page', 1),
            'limit' => (int)$params->fromQuery('limit', 50),
            'rolfRisks' => $params->fromQuery('rolfRisks')
        ];
    }
}
