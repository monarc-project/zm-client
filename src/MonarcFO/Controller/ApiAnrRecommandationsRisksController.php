<?php
/**
 * @link      https://github.com/CASES-LU for the canonical source repository
 * @copyright Copyright (c) Cases is a registered trademark of SECURITYMADEIN.LU
 * @license   MyCases is licensed under the GNU Affero GPL v3 - See license.txt for more information
 */

namespace MonarcFO\Controller;

use MonarcFO\Service\AnrRecommandationRiskService;
use Zend\View\Model\JsonModel;

/**
 * Api Anr Recommandations Risks
 *
 * Class ApiAnrRecommandationsRisksController
 * @package MonarcFO\Controller
 */
class ApiAnrRecommandationsRisksController extends ApiAnrAbstractController
{
    protected $name = 'recommandations-risks';
    protected $dependencies = ['recommandation', 'asset', 'threat', 'vulnerability', 'instance', 'instanceRisk', 'instanceRiskOp'];

    /**
     * @inheritdoc
     */
    public function getList()
    {
        $page = $this->params()->fromQuery('page');
        $limit = $this->params()->fromQuery('limit');
        $order = $this->params()->fromQuery('order');
        $filter = $this->params()->fromQuery('filter');
        $status = $this->params()->fromQuery('status');
        $risk = $this->params()->fromQuery('risk');
        $recommandation = $this->params()->fromQuery('recommandation');
        $op = $this->params()->fromQuery('op');

        $anrId = (int)$this->params()->fromRoute('anrid');
        if (empty($anrId)) {
            throw new \MonarcCore\Exception\Exception('Anr id missing', 412);
        }

        $filterAnd = ['anr' => $anrId];

        if (!is_null($status) && $status != 'all') {
            $filterAnd['status'] = $status;
        }

        if (!is_null($risk)) {
            $fieldName = ($op) ? 'instanceRiskOp' : 'instanceRisk';
            $filterAnd[$fieldName] = intval($risk);
        }

        if (!is_null($recommandation)) {
            $filterAnd['recommandation'] = intval($recommandation);
        }

        /** @var AnrRecommandationRiskService $service */
        $service = $this->getService();

        $entities = $service->getList($page, $limit, $order, $filter, $filterAnd);
        if (count($this->dependencies)) {
            foreach ($entities as $key => $entity) {
                $this->formatDependencies($entities[$key], $this->dependencies);
            }
        }

        return new JsonModel([
            'count' => count($entities),
            $this->name => $entities
        ]);
    }

    /**
     * @inheritdoc
     */
    public function update($id, $data)
    {
        return $this->methodNotAllowed();
    }
}