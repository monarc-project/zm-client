<?php

namespace MonarcFO\Controller;

use MonarcCore\Model\Entity\AbstractEntity;
use Zend\View\Model\JsonModel;

/**
 * Api ANR Objects Controller
 *
 * Class ApiAnrObjectsController
 * @package MonarcFO\Controller
 */
class ApiAnrObjectsController extends ApiAnrAbstractController
{
    protected $name = 'objects';

    /**
     * Get list
     *
     * @return JsonModel
     */
    public function getList()
    {
        $page = $this->params()->fromQuery('page');
        $limit = $this->params()->fromQuery('limit');
        $order = $this->params()->fromQuery('order');
        $filter = $this->params()->fromQuery('filter');
        $asset = (int) $this->params()->fromQuery('asset');
        $category = (int) $this->params()->fromQuery('category');
        $lock = $this->params()->fromQuery('lock');
        $anr = (int) $this->params()->fromRoute('anrid');


        /** @var ObjectService $service */
        $service = $this->getService();
        $objects =  $service->getListSpecific($page, $limit, $order, $filter, $asset, $category, null, $anr, $lock, AbstractEntity::FRONT_OFFICE);

        if ($lock == 'true') {
            foreach($objects as $key => $object){
                $this->formatDependencies($objects[$key], $this->dependencies);
            }
        }

        return new JsonModel(array(
            'count' => $service->getFilteredCount($page, $limit, $order, $filter, $asset, $category, null, $anr),
            $this->name => $objects
        ));
    }
}
