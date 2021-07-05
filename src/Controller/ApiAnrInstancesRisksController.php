<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\Core\Exception\Exception;
use Laminas\View\Model\JsonModel;

/**
 * Api ANR Instances Risks Controller
 *
 * Class ApiAnrInstancesRisksController
 * @package Monarc\FrontOffice\Controller
 */
class ApiAnrInstancesRisksController extends ApiAnrAbstractController
{
    protected $dependencies = ['anr', 'amv', 'asset', 'threat', 'vulnerability', 'instance', 'owner'];
    protected $name = 'instances-risks';

    /**
     * @inheritdoc
     */
    public function update($id, $data)
    {
        $anrId = (int)$this->params()->fromRoute('anrid');
        if (empty($anrId)) {
            throw new Exception('Anr id missing', 412);
        }
        $data['anr'] = $anrId;

        $id = $this->getService()->updateFromRiskTable((int)$id, $data);

        $entity = $this->getService()->getEntity($id);

        if (count($this->dependencies)) {
            foreach ($this->dependencies as $d) {
                unset($entity[$d]);
            }
        }

        return new JsonModel($entity);
    }
}
