<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Zend\View\Model\JsonModel;

/**
 * Api Anr Records Controller
 *
 * Class ApiAnrRecordsController
 * @package Monarc\FrontOffice\Controller
 */
class ApiAnrRecordsController extends ApiAnrAbstractController
{
    protected $name = 'records';
    protected $dependencies = [ 'anr', 'controller', 'representative', 'dpo', 'jointControllers',
                                'personalData', 'internationalTransfers', 'processors', 'recipients'];

    public function getList()
    {
        $anrId = (int)$this->params()->fromRoute('anrid');
        if (empty($anrId)) {
            throw new \Monarc\Core\Exception\Exception('Anr id missing', 412);
        }
        $page = $this->params()->fromQuery('page');
        $limit = $this->params()->fromQuery('limit');
        $order = $this->params()->fromQuery('order');
        $filter = $this->params()->fromQuery('filter');
        $filterAnd = ['anr' => $anrId];

        $service = $this->getService();

        $entities = $service->getList($page, $limit, $order, $filter, $filterAnd);
        if (count($this->dependencies)) {
            foreach ($entities as $key => $entity) {
                $this->formatDependencies($entities[$key], $this->dependencies);
            }
        }
        return new JsonModel(array(
            'count' => $service->getFilteredCount($filter, $filterAnd),
            $this->name => $entities
        ));
    }

    public function get($id)
    {
        $anrId = (int)$this->params()->fromRoute('anrid');
        $entity = $this->getService()->getEntity(['id' => $id]);

        if (empty($anrId)) {
            throw new \Monarc\Core\Exception\Exception('Anr id missing', 412);
        }
        if (!$entity['anr'] || $entity['anr']->get('id') != $anrId) {
            throw new \Monarc\Core\Exception\Exception('Anr ids are different', 412);
        }
        if (count($this->dependencies)) {
            $this->formatDependencies($entity, $this->dependencies);
        }
        return new JsonModel($entity);
    }

    public function formatDependencies(&$entity, $dependencies, $EntityDependency = "", $subField = []) {
        foreach($dependencies as $dependency) {
            if (!empty($entity[$dependency])) {
                if (is_object($entity[$dependency])) {
                    if (is_a($entity[$dependency], '\Monarc\Core\Model\Entity\AbstractEntity')) {
                        if(is_a($entity[$dependency], $EntityDependency)){ //fetch more info
                          $entity[$dependency] = $entity[$dependency]->getJsonArray();
                            if(!empty($subField)){
                              foreach ($subField as $key => $value){
                                $entity[$dependency][$value] = $entity[$dependency][$value] ? $entity[$dependency][$value]->getJsonArray() : [];
                                unset($entity[$dependency][$value]['__initializer__']);
                                unset($entity[$dependency][$value]['__cloner__']);
                                unset($entity[$dependency][$value]['__isInitialized__']);
                              }
                            }
                        }else {
                          $entity[$dependency] = $entity[$dependency]->getJsonArray();
                        }
                        unset($entity[$dependency]['__initializer__']);
                        unset($entity[$dependency]['__cloner__']);
                        unset($entity[$dependency]['__isInitialized__']);
                    }else if(get_class($entity[$dependency]) == 'Doctrine\ORM\PersistentCollection') {
                        $entity[$dependency]->initialize();
                        if($entity[$dependency]->count()){
                            $$dependency = $entity[$dependency]->getSnapshot();
                            $temp = [];
                            foreach($$dependency as $d){
                                if(is_a($d, '\Monarc\FrontOffice\Model\Entity\RecordProcessor')) { //fetch more info
                                    $d = $d->getJsonArray();
                                    if($d['representative']){
                                        $d['representative'] = $d['representative']->getJsonArray();
                                    }
                                    if($d['dpo']){
                                        $d['dpo'] = $d['dpo']->getJsonArray();
                                    }
                                    $temp[] = $d;
                                }
                                else if(is_a($d, '\Monarc\FrontOffice\Model\Entity\RecordPersonalData')) { //fetch more info
                                    $d = $d->getJsonArray();
                                    $d['dataCategories']->initialize();
                                    if($d['dataCategories']->count()){
                                        $dataCategories = $d['dataCategories']->getSnapshot();
                                        $d['dataCategories'] = [];
                                        foreach($dataCategories as $dc){
                                          $tempDataCategory = $dc->toArray();
                                          $d['dataCategories'][] = $tempDataCategory;
                                        }
                                    }
                                    if($d['record']){
                                        $d['record'] = $d['record']->getJsonArray();
                                    }

                                    $temp[] = $d;
                                }
                                else if(is_a($d, '\Monarc\Core\Model\Entity\AbstractEntity')){
                                    $temp[] = $d->getJsonArray();
                                }else{
                                    $temp[] = $d;
                                }
                            }
                            $entity[$dependency] = $temp;
                        }
                    }else if (is_array($entity[$dependency])) {
                        foreach($entity[$dependency] as $key => $value) {
                            if (is_a($entity[$dependency][$key], '\Monarc\Core\Model\Entity\AbstractEntity')) {
                                $entity[$dependency][$key] = $entity[$dependency][$key]->getJsonArray();
                                unset($entity[$dependency][$key]['__initializer__']);
                                unset($entity[$dependency][$key]['__cloner__']);
                                unset($entity[$dependency][$key]['__isInitialized__']);
                            }
                        }
                    }
                }
            }
        }
    }

    public function update($id, $data)
    {
        $anrId = (int)$this->params()->fromRoute('anrid');
        if (empty($anrId)) {
            throw new \Monarc\Core\Exception\Exception('Anr id missing', 412);
        }
        $data['anr'] = $anrId;

        $this->getService()->updateRecord($id, $data);

        return new JsonModel(['status' => 'ok']);
    }

    public function delete($id)
    {
        $anrId = (int)$this->params()->fromRoute('anrid');

        if (empty($anrId)) {
            throw new \Monarc\Core\Exception\Exception('Anr id missing', 412);
        }
        $data['anr'] = $anrId;

        $this->getService()->deleteRecord($id);

        return new JsonModel(['status' => 'ok']);
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

        $id = $this->getService()->create($data);

        return new JsonModel([
            'status' => 'ok',
            'id' => $id,
        ]);
    }
}
