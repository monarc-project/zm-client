<?php
namespace MonarcFO\Controller;

use Zend\View\Model\JsonModel;

abstract class ApiAnrImportAbstractController extends \MonarcCore\Controller\AbstractController
{
    public function create($data)
    {
        $service = $this->getService();

        $anrId = (int) $this->params()->fromRoute('anrid');
        if(empty($anrId)){
        	throw new \Exception('Anr id missing', 412);
        }

        $files = $this->params()->fromFiles('file');
        if(empty($files)){
            throw new \Exception('File missing', 412);
        }
        $data['file'] = $files;

        $id = $service->importFromFile($anrId,$data);

        return new JsonModel(
            array(
                'status' => 'ok',
                'id' => $id,
            )
        );
    }

    public function getList(){
    	return $this->methodNotAllowed();
    }
	public function get($id){
		return $this->methodNotAllowed();
	}
	public function delete($id){
		return $this->methodNotAllowed();
	}
	public function deleteList($data){
		return $this->methodNotAllowed();
	}
	public function update($id, $data){
		return $this->methodNotAllowed();
	}
	public function patch($id, $data){
		return $this->methodNotAllowed();
	}
}

