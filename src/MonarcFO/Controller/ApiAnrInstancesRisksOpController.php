<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
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