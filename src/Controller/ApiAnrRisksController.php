<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\FrontOffice\Service\AnrInstanceRiskService;
use Laminas\Http\Response;
use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\View\Model\JsonModel;

/**
 * Api ANR Risks Controller
 *
 * Class ApiAnrRisksController
 * @package Monarc\FrontOffice\Controller
 */
class ApiAnrRisksController extends AbstractRestfulController
{
    /** @var AnrInstanceRiskService */
    private $anrInstanceRiskService;

    public function __construct(AnrInstanceRiskService $anrInstanceRiskService)
    {
        $this->anrInstanceRiskService = $anrInstanceRiskService;
    }

    public function get($id)
    {
        $anrId = (int)$this->params()->fromRoute('anrid');
        $params = $this->prepareParams();

        if ($this->params()->fromQuery('csv', false)) {
            /** @var Response $response */
            $response = $this->getResponse();
            $response->getHeaders()->addHeaderLine('Content-Type', 'text/csv; charset=utf-8');
            $response->setContent($this->anrInstanceRiskService->getInstanceRisksInCsv($anrId, $id, $params));

            return $response;
        }

        $risks = $this->anrInstanceRiskService->getInstanceRisks($anrId, $id, $params);

        return new JsonModel([
            'count' => \count($risks),
            'risks' => $params['limit'] > 0
                ? \array_slice($risks, ($params['page'] - 1) * $params['limit'], $params['limit'])
                : $risks,
        ]);
    }

    public function getList()
    {
        return $this->get(null);
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

        $id = $this->anrInstanceRiskService->createInstanceRisk($params);

        return new JsonModel([
            'status' => 'ok',
            'id' => $id,
        ]);
    }

    public function delete($id)
    {
        $this->anrInstanceRiskService->deleteInstanceRisk((int)$id, (int)$this->params()->fromRoute('anrid'));

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
