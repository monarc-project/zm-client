<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Laminas\Http\Response;
use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\View\Model\JsonModel;
use Monarc\Core\Exception\Exception;
use Monarc\Core\Model\Entity\Anr;
use Monarc\FrontOffice\Service\AnrInstanceRiskOpService;

/**
 * Api Anr Risks Op Controller
 *
 * Class ApiAnrRisksOpController
 * @package Monarc\FrontOffice\Controller
 */
class ApiAnrRisksOpController extends AbstractRestfulController
{
    private AnrInstanceRiskOpService $anrInstanceRiskOpService;

    public function __construct(AnrInstanceRiskOpService $anrInstanceRiskOpService)
    {
        $this->anrInstanceRiskOpService = $anrInstanceRiskOpService;
    }

    /**
     * @param int $id Instance ID.
     *
     * @return Response|JsonModel
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

        return new JsonModel([
            'count' => \count($risks),
            'oprisks' => $params['limit'] > 0
                ? \array_slice($risks, ($params['page'] - 1) * $params['limit'], $params['limit'])
                : $risks,
        ]);
    }

    public function getList()
    {
        // TODO: apply the middleware.
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');
        $anrId = (int)$this->params()->fromRoute('anrid');
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

        return new JsonModel([
            'count' => \count($risks),
            'oprisks' => $params['limit'] > 0
                ? \array_slice($risks, ($params['page'] - 1) * $params['limit'], $params['limit'])
                : $risks,
        ]);
    }

    public function create($data)
    {
        $anrId = (int)$this->params()->fromRoute('anrid');
        if (empty($anrId)) {
            throw new Exception('Anr id missing', 412);
        }
        $data['anr'] = $anrId;

        return new JsonModel([
            'status' => 'ok',
            'id' => $this->anrInstanceRiskOpService->createSpecificRiskOp($data),
        ]);
    }

    public function delete($id)
    {
        $anrId = (int)$this->params()->fromRoute('anrid');

        $this->anrInstanceRiskOpService->deleteFromAnr($id, $anrId);

        return new JsonModel(['status' => 'ok']);
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
            'page' => $params->fromQuery('page', 1),
            'limit' => $params->fromQuery('limit', 50),
            'rolfRisks' => $params->fromQuery('rolfRisks')
        ];
    }
}
