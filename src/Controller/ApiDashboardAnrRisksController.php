<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\Core\Controller\Handler\AbstractRestfulControllerRequestHandler;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Service\AnrInstanceRiskService;

class ApiDashboardAnrRisksController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;

    public function __construct(private AnrInstanceRiskService $anrInstanceRiskService)
    {
    }

    public function get($id)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        $params = $this->getParsedParams();

        $id = $id === null ? null : (int)$id;
        $instanceRisks = $this->anrInstanceRiskService->getInstanceRisks($anr, $id, $params);

        return $this->getPreparedJsonResponse([
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
