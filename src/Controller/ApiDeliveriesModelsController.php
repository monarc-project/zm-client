<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */
namespace Monarc\FrontOffice\Controller;

use Monarc\Core\Controller\AbstractController;
use Zend\View\Model\JsonModel;

/**
 * Api Doc Models Controller
 *
 * Class ApiDeliveriesModelsController
 * @package Monarc\FrontOffice\Controller
 */
class ApiDeliveriesModelsController extends AbstractController
{
    protected $name = "deliveriesmodels";

    /**
     * @inheritdoc
     */
    public function create($data)
    {
        unset($data['path']);
        $service = $this->getService();
        $file = $this->request->getFiles()->toArray();
        for ($i = 1; $i <= 4; ++$i) {
            if (!empty($file['file'][$i])) {
                // $file['file'][$i]['name'] =  $i . "_" .$data['category'] . "_" . $file['file'][$i]['name'];
                // $file['file'][$i]['name'] =  $i . "_" .$data['category'] . ".docx";
                $file['file'][$i]['name'] =  $data['category'] . ".docx";
                $data['path' . $i] = $file['file'][$i];
            }
        }
        $service->create($data);

        return new JsonModel(array('status' => 'ok'));
    }

    /**
     * @inheritdoc
     */
    public function getList()
    {
        $page = $this->params()->fromQuery('page');
        $limit = $this->params()->fromQuery('limit');
        $order = $this->params()->fromQuery('order');
        $filter = $this->params()->fromQuery('filter');

        $service = $this->getService();

        $entities = $service->getList($page, $limit, $order, $filter);
        if (count($this->dependencies)) {
            foreach ($entities as $key => $entity) {
                $this->formatDependencies($entities[$key], $this->dependencies);
            }
        }

        $pathModel = getenv('APP_CONF_DIR') ? getenv('APP_CONF_DIR') : '';
        foreach($entities as $k => $v){
            for($i=1;$i<=4;$i++){
                $entities[$k]['filename'.$i] = '';
                if(!empty($entities[$k]['path'.$i])){
                    // $name = explode('_',pathinfo($entities[$k]['path'.$i],PATHINFO_BASENAME));
                    // unset($name[0]);
                    $currentPath = $pathModel . $entities[$k]['path'.$i];
                    if (!file_exists($currentPath)) {
                        $entities[$k]['filename'.$i] = '';
                        $entities[$k]['path'.$i] = '';
                    } else {
                        $entities[$k]['filename'.$i] = pathinfo($entities[$k]['path'.$i],PATHINFO_BASENAME);
                        $entities[$k]['path'.$i] = './api/deliveriesmodels/'.$v['id'].'?lang='.$i;
                    }
                }
            }
        }

        return new JsonModel(array(
            'count' => count($entities),
            $this->name => $entities
        ));
    }

    /**
     * @inheritdoc
     */
    public function get($id)
    {
        $entity = $this->getService()->getEntity($id);
        if(!empty($entity)){
            $lang = $this->params()->fromQuery('lang',1);
            $pathModel = getenv('APP_CONF_DIR') ? getenv('APP_CONF_DIR') : '';
            $currentPath = $pathModel . $entity['path'.$lang];
            if(isset($entity['path'.$lang]) && file_exists($currentPath)){
                $name = pathinfo($currentPath)['basename'];

                $fileContents = file_get_contents($currentPath);
                if($fileContents !== false){
                    $response = $this->getResponse();
                    $response->setContent($fileContents);

                    $headers = $response->getHeaders();
                    $headers->clearHeaders()
                        ->addHeaderLine('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')
                        ->addHeaderLine('Content-Disposition', 'attachment; filename="' . utf8_decode($name) . '"')
                        ->addHeaderLine('Content-Length', strlen($fileContents));

                    return $this->response;
                }else{
                    throw new \Monarc\Core\Exception\Exception('Document template not found');
                }
            }else{
                throw new \Monarc\Core\Exception\Exception('Document template not found');
            }
        } else {
            throw new \Monarc\Core\Exception\Exception('Document template not found');
        }
    }

    /**
     * @inheritdoc
     */
    public function update($id, $data)
    {

        $service = $this->getService();
        $file = $this->request->getFiles()->toArray();

        for ($i = 1; $i <= 4; ++$i) {
            unset($data['path'.$i]);
            if (!empty($file['file'][$i])) {
                $data['path' . $i] = $file['file'][$i];
            }
        }
        $service->update($id,$data);
        return new JsonModel(array('status' => 'ok'));
    }

    /**
     * @inheritdoc
     */
    public function patch($id, $data)
    {
        $service = $this->getService();
        $file = $this->request->getFiles()->toArray();
        for ($i = 1; $i <= 4; ++$i) {
            unset($data['path'.$i]);
            if (!empty($file['file'][$i])) {
                $data['path' . $i] = $file['file'][$i];
            }
        }
        $service->patch($id,$data);
        return new JsonModel(array('status' => 'ok'));
    }
}
