<?php
/**
 * @link      https://github.com/CASES-LU for the canonical source repository
 * @copyright Copyright (c) Cases is a registered trademark of SECURITYMADEIN.LU
 * @license   MyCases is licensed under the GNU Affero GPL v3 - See license.txt for more information
 */

namespace MonarcFO\Controller;

use Zend\View\Model\JsonModel;

/**
 * Api ANR Instances Risks Controller
 *
 * Class ApiAnrInstancesRisksController
 * @package MonarcFO\Controller
 */
class ApiAnrInstancesRisksController extends ApiAnrAbstractController
{
    protected $dependencies = ['anr', 'amv', 'asset', 'threat', 'vulnerability', 'instance'];
    protected $name = 'instances-risks';

    /**
     * @inheritdoc
     */
    public function update($id, $data)
    {
        $anrId = (int)$this->params()->fromRoute('anrid');
        if (empty($anrId)) {
            throw new \Exception('Anr id missing', 412);
        }
        $data['anr'] = $anrId;

        $id = $this->getService()->updateFromRiskTable($id, $data);

        $entity = $this->getService()->getEntity($id);

        if (count($this->dependencies)) {
            foreach ($this->dependencies as $d) {
                unset($entity[$d]);
            }
        }

        return new JsonModel($entity);
    }
}