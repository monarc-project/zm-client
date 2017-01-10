<?php

namespace MonarcFO\Controller;

use MonarcCore\Model\Entity\AbstractEntity;
use Zend\View\Model\JsonModel;

class ApiAnrLibraryController extends \MonarcCore\Controller\AbstractController
{
    protected $name = 'categories';

    protected $dependencies = ['anr', 'parent'];

    public function getList()
    {
        $anrId = $this->params()->fromRoute('anrid');
        if(empty($anrId)){
        	throw new \Exception('Anr id missing', 412);
        }

        /** @var ObjectService $service */
        $service = $this->getService();
        $objectsCategories = $service->getCategoriesLibraryByAnr($anrId);

        $this->formatDependencies($objectsCategories, $this->dependencies);

        $fields = ['id', 'label1', 'label2', 'label3', 'label4', 'position', 'objects', 'child'];
        $objectsCategories = $this->recursiveArray($objectsCategories, null, 0, $fields);

        /*
        usort($objectsCategories, function ($a, $b) { return $this->sortCategories($a, $b); });
        foreach ($objectsCategories as &$cat) {
            if (isset($cat['child']) && is_array($cat['child'])) {
                usort($cat['child'], function ($a, $b) {
                    return $this->sortCategories($a, $b);
                });
            }
        }
        */

        return new JsonModel(array(
            $this->name => $objectsCategories
        ));
    }

    private function sortCategories($a, $b) {
        if (isset($a['position']) && isset($b['position'])) {
            //echo "all set for " . $a['label1'] . ' and ' . $b['label1'];
            return ($a['position'] - $b['position']);
        } else if (isset($a['position']) && !isset($b['position'])) {
            return -1;
        } else if (isset($b['position']) && !isset($a['position'])) {
            return 1;
        } else {
            return 0;
        }
    }

    public function get($id)
    {
        return $this->methodNotAllowed();
    }

    /**
     * Create
     *
     * @param mixed $data
     * @return JsonModel
     * @throws \Exception
     */
    public function create($data)
    {
        $anrId = $this->params()->fromRoute('anrid');
        if(empty($anrId)){
        	throw new \Exception('Anr id missing', 412);
        }
        $data['anr'] = $anrId;

        if (!isset($data['objectId'])) {
            throw new \Exception('objectId is missing');
        }

        /** @var ObjectService $service */
        $service = $this->getService();
        $id = $service->attachObjectToAnr($data['objectId'], $anrId, null, null, AbstractEntity::FRONT_OFFICE);

        return new JsonModel(
            array(
                'status' => 'ok',
                'id' => $id,
            )
        );
    }

    public function update($id, $data)
    {
        return $this->methodNotAllowed();
    }

    public function patch($id, $data)
    {
        return $this->methodNotAllowed();
    }

    public function delete($id)
    {
        $anrId = $this->params()->fromRoute('anrid');
        if(empty($anrId)){
        	throw new \Exception('Anr id missing', 412);
        }

        /** @var ObjectService $service */
        $service = $this->getService();
        $service->detachObjectToAnr($id, $anrId);

        return new JsonModel(
            array(
                'status' => 'ok'
            )
        );

    }
}
