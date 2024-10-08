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
use Monarc\FrontOffice\Export\Controller\Traits\ExportResponseControllerTrait;
use Monarc\FrontOffice\Service\AnrInstanceRiskService;
use Laminas\Http\Response;

/**
 * The controller is responsible to fetch instance risks for the whole analysis or a single instance.
 */
class ApiAnrRisksController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;
    use ExportResponseControllerTrait;

    public function __construct(private AnrInstanceRiskService $anrInstanceRiskService)
    {
    }

    /**
     * Fetch instance risks by instance ID or for the whole analysis if id is null.
     *
     * @param int|string|null $id Instance id.
     */
    public function get($id)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');
        $params = $this->prepareParams();
        $id = $id === null ? null : (int)$id;

        if ($this->params()->fromQuery('csv', false)) {
            return $this->prepareCsvExportResponse(
                $this->anrInstanceRiskService->getInstanceRisksInCsv($anr, $id, $params)
            );
        }

        $risks = $this->anrInstanceRiskService->getInstanceRisks($anr, $id, $params);

        return $this->getPreparedJsonResponse([
            'count' => \count($risks),
            'risks' => $params['limit'] > 0
                ? \array_slice($risks, ($params['page'] - 1) * $params['limit'], $params['limit'])
                : $risks,
        ]);
    }

    /**
     * Fetch all the instance risks of the analysis.
     */
    public function getList()
    {
        return $this->get(null);
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
            'page' => (int)$params->fromQuery('page', 1),
            'limit' => (int)$params->fromQuery('limit', 50),
            'amvs' => $params->fromQuery('amvs')
        ];
    }
}
