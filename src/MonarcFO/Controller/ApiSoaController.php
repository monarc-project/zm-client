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
    protected $name = 'Soa-list';
    protected $dependencies = ['anr','measure','category'];

  //  protected $dependencies = ['anr', 'asset', 'object', 'root', 'parent'];





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

      $anrId = (int)$this->params()->fromRoute('anrid');
      if (empty($anrId)) {
          throw new \MonarcCore\Exception\Exception('Anr id missing', 412);
      }

      $filterAnd = ['anr' => $anrId];

      if (!is_null($status) && $status != 'all') {
          $filterAnd['status'] = $status;
      }



      $serviceMeasure = $this->getService('measure');
      $measures = $serviceMeasure->getList($page, $limit, $order, $filter);


      $service = $this->getService();

      $entities = $service->getList($page, $limit, $order, $filter, $filterAnd);
      //set measures dependencies for each soa
      if (count($this->dependencies)) {
          foreach ($entities as $key => $entity) {
              foreach ($measures as $keyy => $entity) {
                $this->formatDependencies($measures[$keyy], ['category']);
                $this->formatDependencies($entities[$key], $this->dependencies);
              }

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

       $anrId = (int)$this->params()->fromRoute('anrid');
       if (empty($anrId)) {
           throw new \MonarcCore\Exception\Exception('Anr id missing', 412);
       }
       if (!$entity['anr'] ) {
           throw new \MonarcCore\Exception\Exception('Anr ids diffence', 412);
       }

       if (count($this->dependencies)) {
           $this->formatDependencies($entity, $this->dependencies);
       }

       return new JsonModel($entity);
   }






}
