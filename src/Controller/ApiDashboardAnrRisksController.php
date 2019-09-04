<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) Cases is a registered trademark of SECURITYMADEIN.LU
 * @license   MyCases is licensed under the GNU Affero GPL v3 - See license.txt for more information
 */

namespace Monarc\FrontOffice\Controller;

use Zend\View\Model\JsonModel;

/**
 * Api Dashboard ANR Risks Controller
 *
 * Class ApiDashboardAnrRisksController
 * @package Monarc\FrontOffice\Controller
 */
class ApiDashboardAnrRisksController extends ApiAnrAbstractController
{
    protected $name = 'risks';

    protected $dependencies = [];

    /**
     * @inheritdoc
     */
    public function get($id)
    {
        $anrId = (int)$this->params()->fromRoute('anrid');
        if (empty($anrId)) {
            throw new \Monarc\Core\Exception\Exception('Anr id missing', 412);
        }
        $params = $this->parseParams();

        $lst = $this->getService()->getRisks($anrId, ['id' => $id], $params);
        return new JsonModel([
            'count' => count($lst),
            $this->name => $params['limit'] > 0 ?
                array_slice($lst, ($params['page'] - 1) * $params['limit'], $params['limit']) : $lst,
        ]);
    }

    /**
     * @inheritdoc
     */
    public function getList()
    {
        $page = $this->params()->fromQuery('page');
        $limit = $this->params()->fromQuery('limit');
        $order = $this->params()->fromQuery('order');
        $filter = $this->params()->fromQuery('filter');


        $anrId = (int)$this->params()->fromRoute('anrid');
        if (empty($anrId)) {
            throw new \Monarc\Core\Exception\Exception('Anr id missing', 412);
        }
        $params = $this->parseParams();

        $service = $this->getService('anr');
        $entities = $service->getList($page, $limit, $order, $filter);
        if (count($this->dependencies)) {
            foreach ($entities as $key => $entity) {
                $this->formatDependencies($entities[$key], $this->dependencies);
            }
        }

        $lst = $this->getService()->getRisks($anrId, null, $params);

        return new JsonModel([
            'count' => count($lst),
            $this->name => $params['limit'] > 0 ?
                array_slice($lst, ($params['page'] - 1) * $params['limit'], $params['limit']) : $lst,
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

    /**
     * @inheritdoc
     */
    public function deleteList($data)
    {
        $this->methodNotAllowed();
    }

    /**
     * @inheritdoc
     */
    public function update($id, $data)
    {
        $this->methodNotAllowed();
    }

    /**
     * @inheritdoc
     */
    public function patch($id, $data)
    {
        $this->methodNotAllowed();
    }
}
