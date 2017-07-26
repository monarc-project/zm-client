<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) Cases is a registered trademark of SECURITYMADEIN.LU
 * @license   MyCases is licensed under the GNU Affero GPL v3 - See license.txt for more information
 */

namespace MonarcFO\Controller;

use Zend\View\Model\JsonModel;

/**
 * Api ANR Instances Risks Op Controller
 *
 * Class ApiAnrInstancesRisksOpController
 * @package MonarcFO\Controller
 */
class ApiAnrInstancesRisksOpController extends ApiAnrAbstractController
{
    protected $name = 'instances-oprisks';

    /**
     * @inheritdoc
     */
    public function update($id, $data)
    {
        $risk = $this->getService()->update($id, $data);
        unset($risk['anr']);
        unset($risk['instance']);
        unset($risk['object']);
        unset($risk['rolfRisk']);

        return new JsonModel($risk);
    }
}