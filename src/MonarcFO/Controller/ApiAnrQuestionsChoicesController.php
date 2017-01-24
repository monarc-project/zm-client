<?php

namespace MonarcFO\Controller;

use MonarcCore\Model\Entity\AbstractEntity;
use MonarcCore\Service\QuestionChoiceService;
use MonarcFO\Model\Entity\QuestionChoice;
use MonarcFO\Model\Table\AnrTable;
use MonarcFO\Model\Table\QuestionChoiceTable;
use MonarcFO\Model\Table\QuestionTable;
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
     * Replace List
     *
     * @param mixed $data
     * @return JsonModel
     * @throws \Exception
     */
    public function replaceList($data)
    {
        $anrId = (int)$this->params()->fromRoute('anrid');
        if (empty($anrId)) {
            throw new \Exception('Anr id missing', 412);
        }

        $this->getService()->replaceList($data, $anrId);

        return new JsonModel(['status' => 'ok']);
    }

}
