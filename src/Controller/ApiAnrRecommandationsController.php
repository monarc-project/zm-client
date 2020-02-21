<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\Core\Exception\Exception;
use Monarc\FrontOffice\Service\AnrRecommandationService;
use Laminas\View\Model\JsonModel;

/**
 * Api Anr Recommandations
 *
 * Class ApiAnrRecommandationsController
 * @package Monarc\FrontOffice\Controller
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
        $status = $this->params()->fromQuery('status', 1);
        $recommandationSet = $this->params()->fromQuery('recommandationSet');

        $anrId = (int)$this->params()->fromRoute('anrid');
        if (empty($anrId)) {
            throw new Exception('Anr id missing', 412);
        }

        $filterAnd = ['anr' => $anrId];

        if ($status !== 'all') {
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
            throw new Exception('Anr id missing', 412);
        }

        if (!isset($data['anr'])) {
            $data['anr'] = $anrId;
        }

        $this->getService()->update($newId, $data);

        return new JsonModel(['status' => 'ok']);
    }

    public function patch($id, $data)
    {
        $anrId = (int)$this->params()->fromRoute('anrid');
        $newId = ['anr' => $anrId, 'uuid' => $id];

        if (empty($anrId)) {
            throw new Exception('Anr id missing', 412);
        }

        if (!isset($data['anr'])) {
            $data['anr'] = $anrId;
        }

        $this->getService()->patch($newId, $data);

        return new JsonModel(['status' => 'ok']);
    }

    /**
     * @inheritdoc
     */
    public function create($data)
    {
        if (isset($data['mass'])) {
            unset($data['mass'], $data['anr']);
            $obj = [];
            foreach ($data as $value) {
                if (empty($value['anr'])) {
                    throw new Exception('Anr id missing', 412);
                }
                $obj[] = $this->getService()->create($value);
            }
            return new JsonModel([
                'status' => 'ok',
                'id' => $obj
            ]);
        }

        if (empty($data['anr'])) {
            throw new Exception('Anr id missing', 412);
        }
        $obj = $this->getService()->create($data);
        return new JsonModel([
            'status' => 'ok',
            'id' => $obj
        ]);
    }
}
