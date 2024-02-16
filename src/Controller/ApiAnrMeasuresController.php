<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\Core\Exception\Exception;
use Monarc\FrontOffice\Service\AnrMeasureService;

class ApiAnrMeasuresController extends ApiAnrAbstractController
{
    protected $name = 'measures';
    protected $dependencies = ['anr', 'category', 'referential', 'amvs', 'linkedMeasures', 'rolfRisks'];

    public function __construct(AnrMeasureService $anrMeasureService)
    {
        parent::__construct($anrMeasureService);
    }

    public function getList()
    {
        $page = $this->params()->fromQuery('page');
        $limit = $this->params()->fromQuery('limit');
        $order = $this->params()->fromQuery('order');
        $filter = $this->params()->fromQuery('filter');
        $status = $this->params()->fromQuery('status');
        $referential = $this->params()->fromQuery('referential');
        $category = $this->params()->fromQuery('category');
        $anrId = (int)$this->params()->fromRoute('anrid');

        if (empty($anrId)) {
            throw new Exception('Anr id missing', 412);
        }

        //make a join because composite key is not supported
        $filterJoin[] = [
            'as' => 'r',
            'rel' => 'referential',
        ];

        if (\is_null($status)) {
            $status = 1;
        }
        $filterAnd = ($status === "all") ? null : ['status' => (int)$status];

        $filterAnd['anr'] = $anrId;

        if ($referential) {
            $filterAnd['r.anr'] = $anrId;
            $filterAnd['r.uuid'] = $referential;
        }
        if ($category) {
            $filterAnd['category'] = (int)$category;
        }

        $service = $this->getService();

        $entities = $service->getList($page, $limit, $order, $filter, $filterAnd);
        if (\count($this->dependencies)) {
            foreach ($entities as $key => $entity) {
                $this->formatDependencies($entities[$key], $this->dependencies);
            }
        }

        return $this->getPreparedJsonResponse([
            'count' => $service->getFilteredCount($filter, $filterAnd, $filterJoin),
            $this->name => $entities,
        ]);
    }

    public function update($id, $data)
    {
        unset($data['rolfRisks']); // not managed for the moement
        $anrId = (int)$this->params()->fromRoute('anrid');
        $ids = ['anr' => $anrId, 'uuid' => $data['uuid']];
        $data['anr'] = $anrId;
        $data ['referential'] = [
            'anr' => $anrId,
            'uuid' => $data['referential'],
        ]; //all the objects is send but we just need the uuid
        $data['category'] ['referential'] = $data ['referential'];
        unset($data['linkedMeasures']);
        unset($data['amvs']);

        return parent::update($ids, $data);
    }

    public function patch($id, $data)
    {
        unset($data['measures']);

        return parent::patch($id, $data);
    }

    public function create($data)
    {
        unset($data['rolfRisks'], $data['linkedMeasures'], $data['amvs']);

        return parent::create($data);
    }

    public function get($id)
    {
        $anrId = (int)$this->params()->fromRoute('anrid');
        $ids = ['uuid' => $id, 'anr' => $anrId];

        return parent::get($ids);
    }

    public function delete($id)
    {
        $anrId = (int)$this->params()->fromRoute('anrid');
        $ids = ['uuid' => $id, 'anr' => $anrId];

        return parent::delete($ids);
    }

    public function deleteList($data)
    {
        $new_data = [];
        $anrId = (int)$this->params()->fromRoute('anrid');
        foreach ($data as $uuid) {
            $new_data[] = ['uuid' => $uuid, 'anr' => $anrId];
        }

        return parent::deleteList($new_data);
    }
}
