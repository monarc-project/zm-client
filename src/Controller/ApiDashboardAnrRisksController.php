<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) Cases is a registered trademark of SECURITYMADEIN.LU
 * @license   MyCases is licensed under the GNU Affero GPL v3 - See license.txt for more information
 */

namespace Monarc\FrontOffice\Controller;

use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\View\Model\JsonModel;
use Monarc\FrontOffice\Service\AnrInstanceRiskService;

class ApiDashboardAnrRisksController extends AbstractRestfulController
{
    private AnrInstanceRiskService $anrInstanceRiskService;

    public function __construct(AnrInstanceRiskService $anrInstanceRiskService)
    {
        $this->anrInstanceRiskService = $anrInstanceRiskService;
    }

    public function get($id)
    {
        $anrId = (int)$this->params()->fromRoute('anrid');

        $params = $this->getParsedParams();

        $instanceRisks = $this->anrInstanceRiskService->getInstanceRisks($anrId, $id, $params);

        return new JsonModel([
            'count' => \count($instanceRisks),
            'risks' => $params['limit'] > 0
                ? \array_slice($instanceRisks, ($params['page'] - 1) * $params['limit'], $params['limit'])
                : $instanceRisks,
        ]);
    }

    public function getList()
    {
        return $this->get(null);
    }

    private function getParsedParams(): array
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
}
