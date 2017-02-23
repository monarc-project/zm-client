<?php
/**
 * @link      https://github.com/CASES-LU for the canonical source repository
 * @copyright Copyright (c) Cases is a registered trademark of SECURITYMADEIN.LU
 * @license   MyCases is licensed under the GNU Affero GPL v3 - See license.txt for more information
 */

namespace MonarcFO\Controller;

use Zend\View\Model\JsonModel;

/**
 * Api Anr Questions Choices Controller
 *
 * Class ApiAnrQuestionsChoicesController
 * @package MonarcFO\Controller
 */
class ApiAnrQuestionsChoicesController extends ApiAnrAbstractController
{
    protected $name = 'choices';

    /**
     * @inheritdoc
     */
    public function replaceList($data)
    {
        $anrId = (int)$this->params()->fromRoute('anrid');
        if (empty($anrId)) {
            throw new \MonarcCore\Exception\Exception('Anr id missing', 412);
        }

        $this->getService()->replaceList($data, $anrId);

        return new JsonModel(['status' => 'ok']);
    }
}