<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2018 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Controller;
use Zend\View\Model\JsonModel;

/**
 * Api ANR Measures Controller
 *
 * Class ApiAnrMeasuresController
 * @package MonarcFO\Controller
 */
class ApiAnrMeasuresController extends ApiAnrAbstractController
{
    protected $name = 'measures';
    protected $dependencies = ['anr', 'category', 'referential',  'amvs'];

    public function getList()
    {
        $page = $this->params()->fromQuery('page');
        $limit = $this->params()->fromQuery('limit');
        $order = $this->params()->fromQuery('order');
        $filter = $this->params()->fromQuery('filter');
        $status = $this->params()->fromQuery('status');
        $referential = $this->params()->fromQuery('referential');
        $category = $this->params()->fromQuery('category');
        $anrId = (int)$this->params()->fromRoute('anrid');

        if (empty($anrId)) {
            throw new \MonarcCore\Exception\Exception('Anr id missing', 412);
        }

        $filterAnd = [];
        $filterJoin[] = ['as' => 'r','rel' => 'referential'];            //make a join because composite key are not supported

        if (is_null($status)) {
            $status = 1;
        }
        $filterAnd = ($status == "all") ? null : ['status' => (int) $status] ;

        $filterAnd = ['anr' => $anrId];

        if ($referential) {
          $filterAnd['r.anr']=$anrId;
          $filterAnd['r.uniqid']= $referential;
        }
        if ($category) {
          $filterAnd['category'] = (int)$category;
        }

        $service = $this->getService();

        $entities = $service->getList($page, $limit, $order, $filter, $filterAnd,$filterJoin);
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

    public function update($id, $data)
    {
      $anrId = (int)$this->params()->fromRoute('anrid');
      $ids = ['anr'=>$anrId,'uniqid'=>$data['uniqid']];
      $data['anr'] = $anrId;
      $data ['referential'] = ['uniqid' => $data['referential']['uniqid'], 'anr' => $anrId]; //all the objects is send but we just need the uniqid
      unset($data['measuresLinked']);
      return parent::update($ids, $data);

    }

    public function create($data)
    {
        $data ['referential'] = $data['referential']['uniqid']; //all the objects is send but we just need the uniqid
        return parent::create($data);
    }

    /**
     * @inheritdoc
     */
    public function get($id)
    {
        $anrId = (int)$this->params()->fromRoute('anrid');
        $ids = ['uniqid'=>$id,'anr'=>$anrId];
        return parent::get($ids);
    }

    /**
     * @inheritdoc
     */
    public function delete($id)
    {
      $anrId = (int)$this->params()->fromRoute('anrid');
      $ids = ['uniqid'=>$id,'anr'=>$anrId];
      return parent::delete($ids);
    }
}
