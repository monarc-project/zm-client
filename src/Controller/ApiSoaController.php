<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\FrontOffice\Model\Entity\Soa;
use Monarc\FrontOffice\Model\Table\SoaTable;
use Monarc\FrontOffice\Service\SoaService;
use Laminas\View\Model\JsonModel;

/**
 * Api Anr Soa Controller
 *
 * Class ApiAnrSoaController
 * @package Monarc\FrontOffice\Controller
 */
class ApiSoaController extends  ApiAnrAbstractController
{
    protected $name = 'soaMeasures';
    protected $dependencies = ['anr','measure'];

  public function getList()
  {
      $page = $this->params()->fromQuery('page');
      $limit = $this->params()->fromQuery('limit');
      $order = $this->params()->fromQuery('order');
      $filter = $this->params()->fromQuery('filter');
      $category = $this->params()->fromQuery('category');
      $referential = $this->params()->fromQuery('referential');


      $anrId = (int)$this->params()->fromRoute('anrid');
      if (empty($anrId)) {
          throw new \Monarc\Core\Exception\Exception('Anr id missing', 412);
      }

      $filterAnd = ['anr' => $anrId];

      if ($referential) {
        if ($category != 0) {
          $filterMeasures['category'] = [
              'op' => 'IN',
              'value' => (array)$category,
          ];
        }
        if ($category == -1) {
          $filterMeasures['category'] = NULL;
        }

        $filterMeasures['r.anr']=$anrId;
        $filterMeasures['r.uuid']= $referential;

        $measureService = $this->getService()->get('measureService');
        $measuresFiltered = $measureService->getList(1, 0, null, null, $filterMeasures);
        $measuresFilteredId = [];
        foreach ($measuresFiltered as $key) {
          array_push($measuresFilteredId,$key['uuid']);
        }
        $filterAnd['m.uuid']= [
            'op' => 'IN',
            'value' => $measuresFilteredId,
        ];
        $filterAnd['m.anr']=$anrId;
      }

      $service = $this->getService();
      $riskService = $this->getService()->get('riskService');
      $riskOpService = $this->getService()->get('riskOpService');

      if($order=='measure')
        $order='m.code';
      else if($order=='-measure')
        $order='-m.code';
      $entities = $service->getList($page, $limit, $order, $filter, $filterAnd);
      if (count($this->dependencies)) {
          foreach ($entities as $key => $entity) {
            $amvs = [];
            $rolfRisks = [];
            foreach ($entity['measure']->amvs as $amv) {
              $amvs[] = $amv->getUuid();
            }
            foreach ($entity['measure']->rolfRisks as $rolfRisk) {
              $rolfRisksp[] = $rolfRisk->getId();
            }
            $entity['measure']->rolfRisks = $riskOpService->getRisksOp($anrId, null, ['rolfRisks' => $rolfRisks, 'limit' => -1 ,'order'=>'cacheNetRisk', 'order_direction' => 'desc']);
            $entity['measure']->amvs = $riskService->getRisks($anrId, null, ['amvs' => $amvs, 'limit' => -1, 'order'=>'maxRisk', 'order_direction' => 'desc']);
            $this->formatDependencies($entities[$key], $this->dependencies, 'Monarc\FrontOffice\Model\Entity\Measure', ['category','referential']);
          }
      }
      return new JsonModel([
          'count' => $service->getFilteredCount($filter, $filterAnd),
          $this->name => $entities
      ]);
  }


   public function get($id)
   {
       $entity = $this->getService()->getEntity($id);

       $anrId = (int)$this->params()->fromRoute('anrid');
       if (empty($anrId)) {
           throw new \Monarc\Core\Exception\Exception('Anr id missing', 412);
       }
       if (!$entity['anr'] ) {
           throw new \Monarc\Core\Exception\Exception('Anr ids diffence', 412);
       }

       if (count($this->dependencies)) {
           $this->formatDependencies($entity, $this->dependencies, 'Monarc\FrontOffice\Model\Entity\Measure', ['category','referential']);
       }

       return new JsonModel($entity);
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
       $data['measure'] = ['anr' => $anrId , 'uuid' => $data['measure']['uuid']];
       return parent::patch($id, $data);
   }

   public function patchList($data)
   {
       $anrId = (int)$this->params()->fromRoute('anrid');
       if (empty($anrId)) {
           throw new \Monarc\Core\Exception\Exception('Anr id missing', 412);
       }

       $created_objects = array();
       foreach ($data as $new_data) {
           $new_data['anr'] = $anrId;
           $new_data['measure'] = ['anr' => $anrId , 'uuid' => $new_data['measure']['uuid']];
           $id = $new_data['id'];
           parent::patch($id, $new_data);
           array_push($created_objects, $id);
       }
       return new JsonModel([
           'status' => 'ok',
           'id' => $created_objects,
       ]);
   }

}
