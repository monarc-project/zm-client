<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2018 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Controller;

use Zend\View\Model\JsonModel;

/**
 * Api ANR Categories Controller
 *
 * Class ApiAnrCategoriesController
 * @package MonarcFO\Controller
 */
class ApiSoaCategoryController extends ApiAnrAbstractController
{
    protected $name = 'categories';
    protected $dependencies = ['anr', 'referential'];

    /**
     * @inheritdoc
     */
    public function getList()
    {
        $anrId = (int)$this->params()->fromRoute('anrid');
        if (empty($anrId)) {
            throw new \MonarcCore\Exception\Exception('Anr id missing', 412);
        }
        $page = $this->params()->fromQuery('page');
        $limit = $this->params()->fromQuery('limit');
        $order = $this->params()->fromQuery('order');
        $filter = $this->params()->fromQuery('filter');
        $status = $this->params()->fromQuery('status');
        $referential = $this->params()->fromQuery('referential');
        $filterJoin[] = ['as' => 'r','rel' => 'referential'];            //make a join because composite key are not supported

        $filterAnd = [];
        if (is_null($status)) {
            $status = 1;
        }
        $filterAnd = ($status == "all") ? null : ['status' => (int) $status] ;
        $filterAnd = ['anr' => $anrId];
        if ($referential) {
          $filterAnd['r.anr']=$anrId;
          $filterAnd['r.uniqid']= $referential;
         }

        $service = $this->getService();

        $entities = $service->getList($page, $limit, $order, $filter, $filterAnd, $filterJoin);
        if (count($this->dependencies)) {
            foreach ($entities as $key => $entity) {
                $this->formatDependencies($entities[$key], $this->dependencies);
            }
        }

        return new JsonModel(array(
            //'count' => $service->getFilteredCount($filter, $filterAnd),
            $this->name => $entities
        ));
    }

}
