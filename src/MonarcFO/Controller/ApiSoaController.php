<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2018 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Controller;

use MonarcFO\Model\Entity\Soa;
use MonarcFO\Model\Table\SoaTable;
use MonarcFO\Service\SoaService;
use Zend\View\Model\JsonModel;

/**
 * Api Anr Soa Controller
 *
 * Class ApiAnrSoaController
 * @package MonarcFO\Controller
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
          throw new \MonarcCore\Exception\Exception('Anr id missing', 412);
      }

      $filterAnd = ['anr' => $anrId];

      if ($referential) {
        if ($category != 0) {
          $filterMeasures['category'] = [
              'op' => 'IN',
              'value' => (array)$category,
          ];
        }

        $filterMeasures['referential'] = (array)$referential;

        $measureService = $this->getService()->get('measureService');

        $measuresFiltered = $measureService->getList(1, null, null, null, $filterMeasures);
        $measuresFilteredId = [];
        foreach ($measuresFiltered as $key) {
          array_push($measuresFilteredId,$key['id']);
        }

        $filterAnd['measure']= [
            'op' => 'IN',
            'value' => $measuresFilteredId,
        ];
      }

      $service = $this->getService();

      $entities = $service->getList($page, $limit, $order, $filter, $filterAnd);
      if (count($this->dependencies)) {
          foreach ($entities as $key => $entity) {
              $this->formatDependencies($entities[$key], $this->dependencies, '\MonarcFO\Model\Entity\Measure', ['category','referential']);
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
           throw new \MonarcCore\Exception\Exception('Anr id missing', 412);
       }
       if (!$entity['anr'] ) {
           throw new \MonarcCore\Exception\Exception('Anr ids diffence', 412);
       }

       if (count($this->dependencies)) {
           $this->formatDependencies($entity, $this->dependencies, '\MonarcFO\Model\Entity\Measure', ['category','referential']);
       }

       return new JsonModel($entity);
   }

}
