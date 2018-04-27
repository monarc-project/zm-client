<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2018 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Controller;

use Zend\View\Model\JsonModel;

/**
 * Api ANR Scales Comments Controller
 *
 * Class ApiAnrScalesCommentsController
 * @package MonarcFO\Controller
 */
class ApiAnrScalesCommentsController extends ApiAnrAbstractController
{
    protected $dependencies = ['anr', 'scale', 'scaleImpactType'];
    protected $name = 'comments';

    /**
     * @inheritdoc
     */
    public function getList()
    {
        $page = $this->params()->fromQuery('page');
        $limit = $this->params()->fromQuery('limit');
        $order = $this->params()->fromQuery('order');
        $filter = $this->params()->fromQuery('filter');

        $anrId = (int)$this->params()->fromRoute('anrid');
        if (empty($anrId)) {
            throw new \MonarcCore\Exception\Exception('Anr id missing', 412);
        }
        $filterAnd = ['anr' => $anrId];

        $scaleId = (int)$this->params()->fromRoute('scaleid');
        if (empty($scaleId)) {
            throw new \MonarcCore\Exception\Exception('Scale id missing', 412);
        }
        $filterAnd['scale'] = $scaleId;

        $service = $this->getService();

        $entities = $service->getList($page, $limit, $order, $filter, $filterAnd);
        if (count($this->dependencies)) {
            foreach ($entities as $key => $entity) {
                $this->formatDependencies($entities[$key], $this->dependencies);
            }
        }

        return new JsonModel([
            'count' => $service->getFilteredCount($filter, $filterAnd),
            $this->name => $entities
        ]);
    }

    /**
     * @inheritdoc
     */
    public function get($id)
    {
        $entity = $this->getService()->getEntity($id);

        $anrId = (int)$this->params()->fromRoute('anrid');
        if (empty($anrId)) {
            throw new \MonarcCore\Exception\Exception('Anr id missing', 412);
        }
        if (!$entity['anr'] || $entity['anr']->get('id') != $anrId) {
            throw new \MonarcCore\Exception\Exception('Anr ids diffence', 412);
        }

        $scaleId = (int)$this->params()->fromRoute('scaleid');
        if (empty($scaleId)) {
            throw new \MonarcCore\Exception\Exception('Scale id missing', 412);
        }
        if (!$entity['scale'] || $entity['scale']->get('id') != $scaleId) {
            throw new \MonarcCore\Exception\Exception('Scale ids diffence', 412);
        }

        if (count($this->dependencies)) {
            $this->formatDependencies($entity, $this->dependencies);
        }

        return new JsonModel($entity);
    }

    /**
     * @inheritdoc
     */
    public function create($data)
    {
        $anrId = (int)$this->params()->fromRoute('anrid');
        if (empty($anrId)) {
            throw new \MonarcCore\Exception\Exception('Anr id missing', 412);
        }
        $data['anr'] = $anrId;
        $scaleId = (int)$this->params()->fromRoute('scaleid');
        if (empty($scaleId)) {
            throw new \MonarcCore\Exception\Exception('Scale id missing', 412);
        }
        $data['scale'] = $scaleId;
		$rightCommLanguage ="comment".$data['langue'];
			$data[$rightCommLanguage] = $data['comment'];
        
			if(isset($data['langue'])) 
			{
				unset($data['comment']);
				unset($data['langue']);
			}       
        		
        $id = $this->getService()->create($data);

        return new JsonModel([
            'status' => 'ok',
            'id' => $id,
        ]);
    }

    /**
     * @inheritdoc
     */
    public function update($id, $data)
    {
        $anrId = (int)$this->params()->fromRoute('anrid');
        if (empty($anrId)) {
            throw new \MonarcCore\Exception\Exception('Anr id missing', 412);
        }
        $data['anr'] = $anrId;
        $scaleId = (int)$this->params()->fromRoute('scaleid');
        if (empty($scaleId)) {
            throw new \MonarcCore\Exception\Exception('Scale id missing', 412);
        }
        $data['scale'] = $scaleId;

        $this->getService()->update($id, $data);

        return new JsonModel(['status' => 'ok']);
    }

    /**
     * @inheritdoc
     */
    public function patch($id, $data)
    {
        $anrId = (int)$this->params()->fromRoute('anrid');
        if (empty($anrId)) {
            throw new \MonarcCore\Exception\Exception('Anr id missing', 412);
        }
        $data['anr'] = $anrId;
        $scaleId = (int)$this->params()->fromRoute('scaleid');
        if (empty($scaleId)) {
            throw new \MonarcCore\Exception\Exception('Scale id missing', 412);
        }
        $data['scale'] = $scaleId;

        $this->getService()->patch($id, $data);

        return new JsonModel(['status' => 'ok']);
    }
}