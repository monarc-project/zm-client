<?php
/**
 * @link      https://github.com/CASES-LU for the canonical source repository
 * @copyright Copyright (c) Cases is a registered trademark of SECURITYMADEIN.LU
 * @license   MyCases is licensed under the GNU Affero GPL v3 - See license.txt for more information
 */

namespace MonarcFO\Controller;

use Zend\View\Model\JsonModel;

/**
 * Api Anr Risks Op Controller
 *
 * Class ApiAnrRisksOpController
 * @package MonarcFO\Controller
 */
class ApiAnrRisksOpController extends ApiAnrAbstractController
{
    protected $name = 'oprisks';

    /**
     * @inheritdoc
     */
    public function get($id)
    {
        $anrId = (int)$this->params()->fromRoute('anrid');
        $params = $this->parseParams();

        if ($this->params()->fromQuery('csv', false)) {
            header('Content-Type: text/csv');
            die($this->getService()->getCsvRisksOp($anrId, ['id' => $id], $params));
        } else {
            $risks = $this->getService()->getRisksOp($anrId, ['id' => $id], $params);
            return new JsonModel([
                'count' => count($risks),
                $this->name => array_slice($risks, ($params['page'] - 1) * $params['limit'], $params['limit'])
            ]);
        }
    }

    /**
     * @inheritdoc
     */
    public function getList()
    {
        $anrId = (int)$this->params()->fromRoute('anrid');
        $params = $this->parseParams();

        if ($this->params()->fromQuery('csv', false)) {
            header('Content-Type: text/csv');
            die($this->getService()->getCsvRisksOp($anrId, null, $params));
        } else {
            $risks = $this->getService()->getRisksOp($anrId, null, $params);
            return new JsonModel([
                'count' => count($risks),
                $this->name => array_slice($risks, ($params['page'] - 1) * $params['limit'], $params['limit'])
            ]);
        }
    }

    /**
     * @inheritdoc
     */
    public function create($data)
    {
        $anrId = (int)$this->params()->fromRoute('anrid');
        if (empty($anrId)) {
            throw new \MonarcCore\Exception\Exception('Anr id missing', 412);
        }
        $data['anr'] = $anrId;

        $id = $this->getService()->createSpecificRiskOp($data);

        return new JsonModel([
            'status' => 'ok',
            'id' => $id,
        ]);
    }

    /**
     * @inheritdoc
     */
    protected function parseParams()
    {
        $keywords = $this->params()->fromQuery("keywords");
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