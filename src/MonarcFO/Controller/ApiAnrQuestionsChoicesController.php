<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
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