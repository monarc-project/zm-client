<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
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
      if (array_keys($data) == range(0, count($data) - 1)) {
        $themeService = $this->getService()->get('themeService');
        $amvItems = ['asset','threat','vulnerability'];
        $itemsToCreate = [];
        for ($i=0; $i < count($amvItems) ; $i++) {
          $thirdPartyService = $this->getService()->get($amvItems[$i] . 'Service');
          $itemsToCreate[$amvItems[$i]] = array_values(
                                            array_filter(
                                              array_column($data,$amvItems[$i]), function($amvItem){
                                                if ($amvItem['uuid'] == null)
                                                  return true;
                                              }
                                            )
                                          );
          $unique_code = array_unique(array_column($itemsToCreate[$amvItems[$i]], 'code'));
          $itemsToCreate[$amvItems[$i]] = array_values(
                                            array_intersect_key($itemsToCreate[$amvItems[$i]],$unique_code)
                                          );
          foreach ($itemsToCreate[$amvItems[$i]] as $key => $new_data) {
            $new_data['anr'] = $anrId;
            if (isset($new_data['theme']) && !is_numeric($new_data['theme'])) {
              $label = implode('',array_values($new_data['theme']));
              $themeFound = $themeService->getList(1,1,null,$label, null);
              if (empty($themeFound)) {
                $new_data['theme']['anr'] = $anrId;
                $new_data['theme'] = $themeService->create($new_data['theme']);
              }else {
                $new_data['theme'] = $themeFound[0]['id'];
              }
            }
            $itemsToCreate[$amvItems[$i]][$key]['uuid'] = $thirdPartyService->create($new_data);
          }
        }

        $amvs = array_map(function($amv) use ($itemsToCreate){
          $uuid_amv = [
            'asset' => ($amv['asset']['uuid'] == null ?
                       $itemsToCreate['asset'][array_search($amv['asset']['code'], array_column($itemsToCreate['asset'],'code'))]['uuid'] :
                       $amv['asset']['uuid']),
            'threat' => ($amv['threat']['uuid'] == null ?
                       $itemsToCreate['threat'][array_search($amv['threat']['code'], array_column($itemsToCreate['threat'],'code'))]['uuid'] :
                       $amv['threat']['uuid']),
            'vulnerability' => ($amv['vulnerability']['uuid'] == null ?
                       $itemsToCreate['vulnerability'][array_search($amv['vulnerability']['code'], array_column($itemsToCreate['vulnerability'],'code'))]['uuid'] :
                       $amv['vulnerability']['uuid']),
          ];
          return $uuid_amv;

        },$data);
        $data = $amvs;
      }
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
