<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\Core\Model\Entity\AbstractEntity;
use Monarc\Core\Model\Entity\MonarcObject;
use Monarc\Core\Service\ObjectService;
use Zend\View\Model\JsonModel;

/**
 * Api ANR Objects Controller
 *
 * Class ApiAnrObjectsController
 * @package Monarc\FrontOffice\Controller
 */
class ApiAnrObjectsController extends ApiAnrAbstractController
{
    protected $name = 'objects';
    protected $dependencies = ['asset', 'category', 'rolfTag'];

    /**
     * @inheritdoc
     */
    public function getList()
    {
        $page = $this->params()->fromQuery('page');
        $limit = $this->params()->fromQuery('limit');
        $order = $this->params()->fromQuery('order');
        $filter = $this->params()->fromQuery('filter');
        $asset = (int)$this->params()->fromQuery('asset');
        $category = (int)$this->params()->fromQuery('category');
        $lock = $this->params()->fromQuery('lock');
        $anr = (int)$this->params()->fromRoute('anrid');

        /** @var ObjectService $service */
        $service = $this->getService();
        $objects = $service->getListSpecific($page, $limit, $order, $filter, $asset, $category, null, $anr, $lock);

        if ($lock == 'true') {
            foreach ($objects as $key => $object) {
                $this->formatDependencies($objects[$key], $this->dependencies);
            }
        }

        return new JsonModel([
            'count' => $service->getFilteredCount($filter, $asset, $category, null, $anr),
            $this->name => $objects
        ]);
    }

    /**
     * @inheritdoc
     */
    public function get($id)
    {
        $anr = (int)$this->params()->fromRoute('anrid');

        /** @var ObjectService $service */
        $service = $this->getService();
        $object = $service->getCompleteEntity($id, MonarcObject::CONTEXT_ANR, $anr);

        if (count($this->dependencies)) {
            $this->formatDependencies($object, $this->dependencies);
        }

        $anrs = [];
        foreach ($object['anrs'] as $key => $anr) {
            $anrs[] = $anr->getJsonArray();
        }
        $object['anrs'] = $anrs;

        return new JsonModel($object);
    }

    /**
     * @inheritdoc
     */
    public function create($data)
    {
        $anrId = (int)$this->params()->fromRoute('anrid');
        if (empty($anrId)) {
            throw new \Monarc\Core\Exception\Exception('Anr id missing', 412);
        }
        $data['anr'] = $anrId;
        $data['asset'] = ['uuid'=>$data['asset'], 'anr'=>$anrId];
        /** @var ObjectService $service */
        $service = $this->getService();
        $id = $service->create($data, true, AbstractEntity::FRONT_OFFICE);

        return new JsonModel([
            'status' => 'ok',
            'id' => $id,
        ]);
    }


    /**
     * @inheritdoc
     */
    public function update($id, $data)
    {
        $anrId = (int)$this->params()->fromRoute('anrid');
        if (empty($anrId)) {
            throw new \Monarc\Core\Exception\Exception('Anr id missing', 412);
        }
        $data['anr'] = $anrId;

        /** @var ObjectService $service */
        $service = $this->getService();
        $service->update($id, $data, AbstractEntity::FRONT_OFFICE);

        return new JsonModel(['status' => 'ok']);
    }

    /**
     * @inheritdoc
     */
    public function patch($id, $data)
    {
        $anrId = (int)$this->params()->fromRoute('anrid');
        if (empty($anrId)) {
            throw new \Monarc\Core\Exception\Exception('Anr id missing', 412);
        }
        $data['anr'] = $anrId;

        /** @var ObjectService $service */
        $service = $this->getService();
        $service->patch($id, $data, AbstractEntity::FRONT_OFFICE);

        return new JsonModel(['status' => 'ok']);
    }
}
