<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\FrontOffice\Service\AnrRiskService;
use Zend\Http\Response;
use Zend\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;

/**
 * Api ANR Risks Controller
 *
 * Class ApiAnrRisksController
 * @package Monarc\FrontOffice\Controller
 */
class ApiAnrRisksController extends AbstractRestfulController
{
    /** @var AnrRiskService */
    private $anrRiskService;

    public function __construct(AnrRiskService $anrRiskService)
    {
        $this->anrRiskService = $anrRiskService;
    }

    public function get($id)
    {
        $anrId = (int)$this->params()->fromRoute('anrid');
        $params = $this->prepareParams();

        if ($this->params()->fromQuery('csv', false)) {
            /** @var Response $response */
            $response = $this->getResponse();
            $response->getHeaders()->addHeaderLine('Content-Type', 'text/csv; charset=utf-8');
            $response->setContent($this->anrRiskService->getCsvRisks($anrId, ['id' => $id], $params));

            return $response;
        }

        $risks = $this->anrRiskService->getRisks($anrId, ['id' => $id], $params);

        return new JsonModel([
            'count' => \count($risks),
            'risks' => $params['limit'] > 0
                ? \array_slice($risks, ($params['page'] - 1) * $params['limit'], $params['limit'])
                : $risks,
        ]);
    }

    public function getList()
    {
        $anrId = (int)$this->params()->fromRoute('anrid');
        $params = $this->prepareParams();

        if ($this->params()->fromQuery('csv', false)) {
            /** @var Response $response */
            $response = $this->getResponse();
            $response->getHeaders()->addHeaderLine('Content-Type', 'text/csv; charset=utf-8');
            $response->setContent($this->anrRiskService->getCsvRisks($anrId, null, $params));

            return $response;
        }

        $risks = $this->anrRiskService->getRisks($anrId, null, $params);

        return new JsonModel([
            'count' => \count($risks),
            'risks' => $params['limit'] > 0
                ? \array_slice($risks, ($params['page'] - 1) * $params['limit'], $params['limit'])
                : $risks,
        ]);
    }

    public function create($data)
    {
        $params = [
            'anr' => (int)$this->params()->fromRoute('anrid'),
            'instance' => $data['instance'],
            'specific' => $data['specific'],
            'threat' => [
                'uuid' => $data['threat'],
                'anr' => $data['anrId'],
            ],
            'vulnerability' => [
                'uuid' => $data['vulnerability'],
                'anr' => $data['anrId'],
            ],
        ];

        $id = $this->anrRiskService->create($params);

        return new JsonModel([
            'status' => 'ok',
            'id' => $id,
        ]);
    }

    public function delete($id)
    {
        $anrId = (int)$this->params()->fromRoute('anrid');

        $this->anrRiskService->deleteFromAnr($id, $anrId);

        return new JsonModel(['status' => 'ok']);
    }

    protected function prepareParams(): array
    {
        $params = $this->params();

        return [
            'keywords' => trim($params->fromQuery('keywords', '')),
            'kindOfMeasure' => $params->fromQuery('kindOfMeasure'),
            'order' => $params->fromQuery('order', 'maxRisk'),
            'order_direction' => $params->fromQuery('order_direction', 'desc'),
            'thresholds' => $params->fromQuery('thresholds'),
            'page' => $params->fromQuery('page', 1),
            'limit' => $params->fromQuery('limit', 50),
            'amvs' => $params->fromQuery('amvs')
        ];
    }
}
