<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Zend\View\Model\JsonModel;

/**
 * Api ANR Amvs Controller
 *
 * Class ApiAnrAmvsController
 * @package Monarc\FrontOffice\Controller
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
            throw new \Monarc\Core\Exception\Exception('Anr id missing', 412);
        }

        $filterAnd = ['anr' => $anrId];

        if (is_null($status)) {
            $status = 1;
        }

        if ($status != 'all') {
            $filterAnd['status'] = (int)$status;
        }
        if ($asset !=null) {
            $filterAnd['a.uuid'] = $asset;
            $filterAnd['a.anr'] = $anrId;
        }

        if (!empty($amvid)) {
            $filterAnd['uuid'] = [
                'op' => '!=',
                'value' => $amvid,
            ];
        }
        if($order == 'asset')
          $order = 'a.code';
        if($order == '-asset')
          $order = '-a.code';
        if($order == 'threat')
          $order = 'th.code';
        if($order == '-threat')
          $order = '-th.code';
        if($order == 'vulnerability')
          $order = 'v.code';
        if($order == '-vulnerability')
          $order = '-v.code';

        $service = $this->getService();

        $entities = $service->getList($page, $limit, $order, $filter, $filterAnd);
        if (count($this->dependencies)) {
            foreach ($entities as $key => $entity) {
                $this->formatDependencies($entities[$key], $this->dependencies, 'Monarc\FrontOffice\Model\Entity\Measure', ['referential']);
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
      $anrId = (int)$this->params()->fromRoute('anrid');
      if (empty($anrId)) {
          throw new \Monarc\Core\Exception\Exception('Anr id missing', 412);
      }
      $id = ['uuid'=>$id, 'anr' => $anrId];
      $entity = $this->getService()->getEntity($id);

        if (count($this->dependencies)) {
            $this->formatDependencies($entity, $this->dependencies, 'Monarc\FrontOffice\Model\Entity\Measure', ['referential']);
        }

        // Find out the entity's implicitPosition and previous
        if ($entity['position'] == 1) {
            $entity['implicitPosition'] = 1;
        } else {
            // We're not at the beginning, get all AMV links of the same asset, and figure out position and previous
            $amvsAsset = $this->getService()->getList(1, 0, 'position', null, ['a.anr' => $anrId, 'a.uuid' =>$entity['asset']['uuid']->toString()]);

            $i = 0;
            foreach ($amvsAsset as $amv) {
                if ($amv['uuid']->toString() == $entity['uuid']->toString()) {
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
        $data['measures'] = $this->addAnrId($data['measures']);
      unset($data ['referential'] );
      return parent::create($data);
    }

    public function update($id,$data)
    {
      $anrId = (int)$this->params()->fromRoute('anrid');
      if(count($data['measures'])>0)
        $data['measures'] = $this->addAnrId($data['measures']);

      unset($data ['referential'] );
      return parent::update($id, $data);
    }

    public function patchList($data)
    {
      $service = $this->getService();
      $data['toReferential'] = $this->addAnrId($data['toReferential']);
      $service->createLinkedAmvs($data['fromReferential'],$data['toReferential'],$anrId);

      return new JsonModel([
          'status' =>  'ok',
      ]);

    }
}
