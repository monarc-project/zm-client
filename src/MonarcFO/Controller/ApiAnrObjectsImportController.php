<?php

namespace MonarcFO\Controller;

use Zend\View\Model\JsonModel;

/**
 * Api ANR Objects Import Controller
 *
 * Class ApiAnrObjectsImportController
 * @package MonarcFO\Controller
 */
class ApiAnrObjectsImportController extends ApiAnrImportAbstractController
{
	protected $name = 'objects';
	public function getList(){
		$anrId = (int) $this->params()->fromRoute('anrid');
        if(empty($anrId)){
        	throw new \Exception('Anr id missing', 412);
        }
        $objects = $this->getService()->getCommonObjects($anrId);
        return new JsonModel(array(
            'count' => count($objects),
            $this->name => $objects,
        ));
	}
}
