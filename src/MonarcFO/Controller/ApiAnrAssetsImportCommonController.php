<?php
namespace MonarcFO\Controller;

use Zend\View\Model\JsonModel;

class ApiAnrAssetsImportCommonController extends ApiAnrImportAbstractController
{
	protected $name = "assets";

	public function getList(){
        $service = $this->getService();

        $anrId = (int) $this->params()->fromRoute('anrid');
        if(empty($anrId)){
        	throw new \Exception('Anr id missing', 412);
        }

        $entities = $service->getListAssets($anrId);

        return new JsonModel(array(
            'count' => count($entities),
            $this->name => $entities
        ));
    }

    public function get($id){
    	$service = $this->getService();

        $anrId = (int) $this->params()->fromRoute('anrid');
        if(empty($anrId)){
        	throw new \Exception('Anr id missing', 412);
        }

        $entitie = $service->getAsset($anrId,$id);

        return new JsonModel($entitie);
    }
}

