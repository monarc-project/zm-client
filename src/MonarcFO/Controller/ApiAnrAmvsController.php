<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Controller;

use Zend\View\Model\JsonModel;

/**
 * Api ANR Amvs Controller
 *
 * Class ApiAnrAmvsController
 * @package MonarcFO\Controller
 */
class ApiAnrAmvsController extends ApiAnrAbstractController
{
    protected $name = 'amvs';
    protected $dependencies = ['asset', 'threat', 'vulnerability', 'measures'];


    public function getList()
    {
        $page = $this->params()->fromQuery('page');
        $limit = $this->params()->fromQuery('limit');
        $order = $this->params()->fromQuery('order');
        $filter = $this->params()->fromQuery('filter');
        $status = $this->params()->fromQuery('status');
        $asset = $this->params()->fromQuery('asset');
        $amvid = $this->params()->fromQuery('amvid');

        $anrId = (int)$this->params()->fromRoute('anrid');
        if (empty($anrId)) {
            throw new \MonarcCore\Exception\Exception('Anr id missing', 412);
        }

        $filterAnd = ['anr' => $anrId];

        if (is_null($status)) {
            $status = 1;
        }

        if ($status != 'all') {
            $filterAnd['status'] = (int)$status;
        }
        if ($asset > 0) {
            $filterAnd['asset'] = (int)$asset;
        }

        if (!empty($amvid)) {
            $filterAnd['id'] = [
                'op' => '!=',
                'value' => (int)$amvid,
            ];
        }

        $service = $this->getService();

        $entities = $service->getList($page, $limit, $order, $filter, $filterAnd);
        if (count($this->dependencies)) {
            foreach ($entities as $key => $entity) {
                $this->formatDependencies($entities[$key], $this->dependencies, '\MonarcFO\Model\Entity\Measure', ['referential']);
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
    public function get($id)
    {
        $entity = $this->getService()->getEntity($id);

        if (count($this->dependencies)) {
            $this->formatDependencies($entity, $this->dependencies, '\MonarcFO\Model\Entity\Measure', ['referential']);
        }

        // Find out the entity's implicitPosition and previous
        if ($entity['position'] == 1) {
            $entity['implicitPosition'] = 1;
        } else {
            // We're not at the beginning, get all AMV links of the same asset, and figure out position and previous
            $amvsAsset = $this->getService()->getList(1, 0, 'position', null, ['asset' => $entity['asset']['id']]);

            $i = 0;
            foreach ($amvsAsset as $amv) {
                if ($amv['id'] == $entity['id']) {
                    if ($i == count($amvsAsset) - 1) {
                        $entity['implicitPosition'] = 2;
                    } else {
                        if ($i == 0) {
                            $entity['implicitPosition'] = 1;
                            $entity['previous'] = null;
                        } else {
                            $entity['implicitPosition'] = 3;
                            $entity['previous'] = $amvsAsset[$i - 1];
                            $this->formatDependencies($entity['previous'], $this->dependencies);
                        }
                    }

                    break;
                }

                ++$i;
            }
        }

        return new JsonModel($entity);
    }

    public function create($data)
    {
      $anrId = (int)$this->params()->fromRoute('anrid');
      if(count($data['measures'])>0)
      foreach ($data['measures'] as $key => $value) {
        $data['measures'][$key] = ['uuid'=>$value, 'anr' => $anrId];
      }
      unset($data ['referential'] );
      return parent::create($data);
    }

    public function update($id,$data)
    {
      $anrId = (int)$this->params()->fromRoute('anrid');
      if(count($data['measures'])>0)
      foreach ($data['measures'] as $key => $value) {
        $data['measures'][$key] = ['uuid'=>$value, 'anr' => $anrId];
      }
      $this->getService()->createLinkedAmvs('98ca84fb-db87-11e8-ac77-0800279aaa2b','d50045a6-c3a7-4190-a340-96b44ee478be',$anrId);

      unset($data ['referential'] );
      return parent::update($id, $data);
    }
}
