<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Controller;

use Zend\View\Model\JsonModel;

/**
 * Api Anr Record Processors Controller
 *
 * Class ApiAnrRecordProcessorsController
 * @package MonarcFO\Controller
 */
class ApiAnrRecordProcessorsController extends ApiAnrAbstractController
{
    protected $name = 'record-processors';
    protected $dependencies = ['anr', 'controllers'];

    public function get($id)
    {
        $anrId = (int)$this->params()->fromRoute('anrid');
        $entity = $this->getService()->getEntity(['anr' => $anrId, 'id' => $id]);

        if (empty($anrId)) {
            throw new \MonarcCore\Exception\Exception('Anr id missing', 412);
        }
        if (!$entity['anr'] || $entity['anr']->get('id') != $anrId) {
            throw new \MonarcCore\Exception\Exception('Anr ids are different', 412);
        }

        if (count($this->dependencies)) {
            $this->formatDependencies($entity, $this->dependencies);
        }

        return new JsonModel($entity);
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
        $this->getService()->updateProcessor($id, $data);

        return new JsonModel(['status' => 'ok']);
    }

    /**
     * @inheritdoc
     */
    public function create($data)
    {
        return $this->methodNotAllowed();
    }

    /**
     * @inheritdoc
     */
    public function patch($id, $data)
    {
        return $this->methodNotAllowed();
    }

    /**
     * @inheritdoc
     */
    public function delete($id)
    {
        return $this->methodNotAllowed();
    }
}
