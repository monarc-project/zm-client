<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Controller;
use Zend\View\Model\JsonModel;

/**
 * Api Anr Recommandations
 *
 * Class ApiAnrRecommandationsController
 * @package MonarcFO\Controller
 */
class ApiAnrRecommandationsController extends ApiAnrAbstractController
{
    protected $name = 'recommandations';
    protected $dependencies = ['anr', 'recommandationSet'];

    public function update($id, $data)
    {
        $anrId = (int)$this->params()->fromRoute('anrid');
        $newId = ['anr'=> $anrId, 'uuid' => $id];

        if (empty($anrId)) {
            throw new \MonarcCore\Exception\Exception('Anr id missing', 412);
        }

        if(!isset($data['anr'])) $data['anr'] = $anrId;

        $this->getService()->update($newId, $data);

        return new JsonModel(['status' => 'ok']);
    }

    public function patch($id, $data)
    {
        $anrId = (int)$this->params()->fromRoute('anrid');
        $newId = ['anr'=> $anrId, 'uuid' => $id];

        if (empty($anrId)) {
            throw new \MonarcCore\Exception\Exception('Anr id missing', 412);
        }

        if(!isset($data['anr'])) $data['anr'] = $anrId;

        $this->getService()->patch($newId, $data);

        return new JsonModel(['status' => 'ok']);
    }

    


}