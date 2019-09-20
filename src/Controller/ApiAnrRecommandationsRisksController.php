<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\FrontOffice\Service\AnrRecommandationRiskService;
use Zend\View\Model\JsonModel;

/**
 * Api Anr Recommandations Risks
 *
 * Class ApiAnrRecommandationsRisksController
 * @package Monarc\FrontOffice\Controller
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
            throw new \Monarc\Core\Exception\Exception('Anr id missing', 412);
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
            $filterAnd['r.uuid'] = $recommandation;
            $filterAnd['r.anr'] = $anrId;
        }

        /** @var AnrRecommandationRiskService $service */
        $service = $this->getService();

        $entities = $service->getList($page, $limit, $order, $filter, $filterAnd);
        if (count($this->dependencies)) {
            foreach ($entities as $key => $entity) {
                $this->formatDependencies($entities[$key], $this->dependencies, 'Monarc\FrontOffice\Model\Entity\Recommandation', ['recommandationSet']);
            }
        }

        return new JsonModel([
            'count' => $service->getFilteredCount($filter, $filterAnd),
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
