<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Laminas\Http\Response;
use Monarc\FrontOffice\Model\Entity\Instance;
use Monarc\FrontOffice\Service\AnrInstanceRiskOpService;
use Monarc\FrontOffice\Service\AnrInstanceService;
use Laminas\View\Model\JsonModel;

/**
 * Api ANR Instances Controller
 *
 * Class ApiAnrInstancesController
 * @package Monarc\FrontOffice\Controller
 */
class ApiAnrInstancesController extends ApiAnrAbstractController
{
    protected $name = 'instances';

    protected $dependencies = ['anr', 'asset', 'object', 'root', 'parent'];

    /**
     * @inheritdoc
     */
    public function getList()
    {
        $anrId = (int)$this->params()->fromRoute('anrid');

        /** @var AnrInstanceService $service */
        $service = $this->getService();
        $instances = $service->findByAnr($anrId);
        return new JsonModel([
            $this->name => $instances
        ]);
    }

    /**
     * @inheritdoc
     */
    public function update($id, $data)
    {
        $anrId = (int)$this->params()->fromRoute('anrid');

        /** @var AnrInstanceService $service */
        $service = $this->getService();
        $service->updateInstance($anrId, $id, $data);

        return new JsonModel(['status' => 'ok']);
    }

    /**
     * @inheritdoc
     */
    public function patch($id, $data)
    {
        $anrId = (int)$this->params()->fromRoute('anrid');

        /** @var AnrInstanceService $service */
        $service = $this->getService();
        $service->patchInstance($anrId, $id, $data, [], false);

        return new JsonModel(['status' => 'ok']);
    }

    /**
     * @inheritdoc
     */
    public function get($id)
    {
        $anrId = (int)$this->params()->fromRoute('anrid');
        /** @var AnrInstanceService $service */
        $service = $this->getService();
        $entity = $service->getEntityByIdAndAnr($id, $anrId);
        $params = $this->parseParams();

        if ($this->params()->fromQuery('csv', false)) {
            /** @var AnrInstanceRiskOpService $anrInstanceRiskOpService */
            $anrInstanceRiskOpService = $this->getService()->get('instanceRiskOpService');

            return $this->setCsvResponse($anrInstanceRiskOpService->getOperationalRisksInCsv($anrId, $id, $params));
        }
        if ($this->params()->fromQuery('csvInfoInst', false)) {
            return $this->setCsvResponse($service->getCsvRisks($anrId, $id, $params));
        }

        if (\count($this->dependencies)) {
            $this->formatDependencies($entity, $this->dependencies);
        }

        return new JsonModel($entity);
    }

    /**
     * @inheritdoc
     */
    public function create($data)
    {
        $anrId = (int)$this->params()->fromRoute('anrid');

        //verification required
        $required = ['object', 'parent', 'position'];
        $missing = [];
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                $missing[] = $field . ' missing';
            }
        }
        if (count($missing)) {
            throw new \Monarc\Core\Exception\Exception(implode(', ', $missing), 412);
        }

        $data['c'] = isset($data['c']) ? $data['c'] : '-1';
        $data['i'] = isset($data['i']) ? $data['i'] : '-1';
        $data['d'] = isset($data['d']) ? $data['d'] : '-1';

        /** @var InstanceService $service */
        $service = $this->getService();
        $id = $service->instantiateObjectToAnr($anrId, $data, true, true, Instance::MODE_CREA_ROOT);

        return new JsonModel([
            'status' => 'ok',
            'id' => $id,
        ]);
    }

    /**
     * Helper function to parse query parameters
     * @return array The sorted parameters
     */
    protected function parseParams()
    {
        $keywords = trim($this->params()->fromQuery("keywords", ''));
        $kindOfMeasure = $this->params()->fromQuery("kindOfMeasure");
        $order = $this->params()->fromQuery("order", "maxRisk");
        $order_direction = $this->params()->fromQuery("order_direction", "desc");
        $thresholds = $this->params()->fromQuery("thresholds");
        $page = $this->params()->fromQuery("page", 1);
        $limit = $this->params()->fromQuery("limit", 50);

        return [
            'keywords' => $keywords,
            'kindOfMeasure' => $kindOfMeasure,
            'order' => $order,
            'order_direction' => $order_direction,
            'thresholds' => $thresholds,
            'page' => $page,
            'limit' => $limit
        ];
    }

    protected function setCsvResponse(string $content): Response
    {
        /** @var Response $response */
        $response = $this->getResponse();
        $response->getHeaders()->addHeaderLine('Content-Type', 'text/csv; charset=utf-8');
        $response->setContent($content);

        return $response;
    }
}
