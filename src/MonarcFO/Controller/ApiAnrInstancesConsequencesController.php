<?php
/**
 * @link      https://github.com/CASES-LU for the canonical source repository
 * @copyright Copyright (c) Cases is a registered trademark of SECURITYMADEIN.LU
 * @license   MyCases is licensed under the GNU Affero GPL v3 - See license.txt for more information
 */

namespace MonarcFO\Controller;

use Zend\View\Model\JsonModel;

/**
 * Api Instance Consequences Controller
 *
 * Class ApiAnrInstancesConsequencesController
 * @package MonarcFO\Controller
 */
class ApiAnrInstancesConsequencesController extends ApiAnrAbstractController
{
    protected $name = 'instances-consequences';

    /**
     * Patch
     *
     * @param mixed $id
     * @param mixed $data
     * @return JsonModel
     */
    public function patch($id, $data)
    {
        $data['anr'] = (int)$this->params()->fromRoute('anrid');

        $this->getService()->patchConsequence($id, $data);

        return new JsonModel(['status' => 'ok']);
    }
}