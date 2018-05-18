<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2018 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Service;
use MonarcFO\Model\Entity\Soa;
use MonarcFO\Model\Table\SoaTable;
use MonarcCore\Model\Entity\AbstractEntity;
use MonarcFO\Model\Entity\Amv;
use MonarcFO\Model\Table\AmvTable;
use MonarcFO\Model\Entity\InstanceRisk;
use MonarcFO\Model\Table\InstanceRiskTable;
use MonarcFO\Model\Entity\Measure;
use MonarcFO\Model\Table\MeasureTable;

//use MonarcFO\Model\Entity\Measure;
//use MonarcCore\Service\MeasureService;


/**
 * @package MonarcFO\Service
 */

 class SoaService extends \MonarcCore\Service\AbstractService
 {

 protected $table;
  protected $entity;


   /**
    * Get List
    *
    * @param int $page
    * @param int $limit
    * @param null $order
    * @param null $filter
    * @param null $filterAnd
    * @return mixed
    */
   public function getList($page = 1, $limit = 25, $order = null, $filter = null, $filterAnd = null)
   {
     //file_put_contents('php://stderr', print_r('testservice', TRUE));

       return $this->get('table')->fetchAllFiltered(
           array_keys($this->get('entity')->getJsonArray()),
           $page,
           $limit,
           $this->parseFrontendOrder($order),
           $this->parseFrontendFilter($filter, []),

           $filterAnd
      /*     $this->parseFrontendFilter(
               $filter,
               ['id', ...]
               )*/
       );
   }








   public function patch($id, $data)
   {

       /** @var Soa $entity */
       $entity = $this->get('table')->getEntity($id);
       if (!$entity) {
           throw new \MonarcCore\Exception\Exception('Entity does not exist', 412);
       }
       // If we try to override this object's ANR, make some sanity and security checks. Ensure the data's ANR matches
       // the existing ANR, and that we have the rights to edit it.
       if (!empty($data['anr'])) {


           $connectedUser = $this->get('table')->getConnectedUser();

           /** @var UserAnrTable $userAnrTable */
           $userAnrTable = $this->get('userAnrTable');
           if ($userAnrTable) {
               $rights = $userAnrTable->getEntityByFields(['user' => $connectedUser['id'], 'anr' => $entity->anr->id]);
               $rwd = 0;
               foreach ($rights as $right) {
                   if ($right->rwd == 1) {
                       $rwd = 1;
                   }
               }

               if (!$rwd) {
                   throw new \MonarcCore\Exception\Exception('You are not authorized to do this action', 412);
               }
           }
       }

       $entity->setDbAdapter($this->get('table')->getDb());
       $entity->setLanguage($this->getLanguage());
       foreach ($this->dependencies as $dependency) {
           if ((!isset($data[$dependency])) && ($entity->$dependency)) {
               $data[$dependency] = $entity->$dependency->id;
           }
       }

       // Pass our new data to the entity. This might throw an exception if some data is invalid.
       $entity->exchangeArray($data, true);

       $dependencies = (property_exists($this, 'dependencies')) ? $this->dependencies : [];
       $this->setDependencies($entity, $dependencies);

       return $this->get('table')->save($entity);
   }









}
