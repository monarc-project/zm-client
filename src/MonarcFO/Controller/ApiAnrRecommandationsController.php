<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Controller;

use Zend\View\Model\JsonModel;

/**
 * Api Anr Recommandations
 *
 * Class ApiAnrRecommandationsController
 * @package MonarcFO\Controller
 */
class ApiAnrRecommandationsController extends ApiAnrAbstractController
{
    protected $name = 'recommandations';
    protected $dependencies = ['anr', 'recommandationSet'];

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
        $recommandationSet = $this->params()->fromQuery('recommandationSet');

        $anrId = (int)$this->params()->fromRoute('anrid');
        if (empty($anrId)) {
            throw new \MonarcCore\Exception\Exception('Anr id missing', 412);
        }

        $filterAnd = ['anr' => $anrId];

        if (is_null($status)) {
            $status = 1;
        }

        if (!is_null($status) && $status != 'all') {
            $filterAnd['status'] = $status;
        }

        if (!is_null($recommandationSet)) {
            $filterAnd['r.uuid'] = $recommandationSet;
            $filterAnd['r.anr'] = $anrId;
        }

        /** @var AnrRecommandationService $service */
        $service = $this->getService();

        $entities = $service->getList($page, $limit, $order, $filter, $filterAnd);
        if (count($this->dependencies)) {
            foreach ($entities as $key => $entity) {
                $this->formatDependencies($entities[$key], $this->dependencies);
            }
        }

        return new JsonModel([
            'count' => $service->getFilteredCount($filter, $filterAnd),
            $this->name => $entities
        ]);
    }

    public function update($id, $data)
    {
        $anrId = (int)$this->params()->fromRoute('anrid');
        $newId = ['anr' => $anrId, 'uuid' => $id];

        if (empty($anrId)) {
            throw new \MonarcCore\Exception\Exception('Anr id missing', 412);
        }

        if (!isset($data['anr'])) $data['anr'] = $anrId;

        $this->getService()->update($newId, $data);

        return new JsonModel(['status' => 'ok']);
    }

    public function patch($id, $data)
    {
        $anrId = (int)$this->params()->fromRoute('anrid');
        $newId = ['anr' => $anrId, 'uuid' => $id];

        if (empty($anrId)) {
            throw new \MonarcCore\Exception\Exception('Anr id missing', 412);
        }

        if (!isset($data['anr'])) $data['anr'] = $anrId;

        $this->getService()->patch($newId, $data);

        return new JsonModel(['status' => 'ok']);
    }

    /**
     * @inheritdoc
     */
    public function create($data)
    {
        if (isset($data['mass'])) {
            unset($data['mass']);
            unset($data['anr']);
            foreach ($data as $value) {
                if (empty($value['anr'])) {
                    throw new \MonarcCore\Exception\Exception('Anr id missing', 412);
                }
                $obj = $this->getService()->create($value);
            }
            return new JsonModel([
                'status' => 'ok',
            ]);
        } else {
            if (empty($data['anr'])) {
                throw new \MonarcCore\Exception\Exception('Anr id missing', 412);
            }
            $obj = $this->getService()->create($data);
            return new JsonModel([
                'status' => 'ok'
            ]);
        }
    }
}
