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
     */
    public function replaceList($data) {
        $anrId = (int) $this->params()->fromRoute('anrid');
        if(empty($anrId)){
            throw new \Exception('Anr id missing', 412);
        }

        /** @var QuestionChoiceService $service */
        $service = $this->getService();

        /** @var QuestionChoiceTable $table */
        $table = $service->get('table');

        // Remove existing choices
        $questions = $table->fetchAllFiltered(['id'], 1, 0, null, null, ['question' => $data['questionId']]);
        foreach ($questions as $q) {
            $table->delete($q['id']);
        }

        /** @var QuestionTable $questionTable */
        $questionTable = $service->get('questionTable');
        $question = $questionTable->getEntity($data['questionId']);

        /** @var AnrTable $anrTable */
        $anrTable = $service->get('anrTable');
        $anr = $anrTable->getEntity($anrId);

        // Add new choices
        $pos = 1;
        foreach ($data['choice'] as $c) {
            $c['position'] = $pos;
            unset($c['question']);

            /** @var QuestionChoice $choiceEntity */
            $choiceEntity = new QuestionChoice();
            $choiceEntity->setQuestion($question);
            $choiceEntity->setAnr($anr);
            $choiceEntity->squeezeAutoPositionning(true);
            $choiceEntity->exchangeArray($c);
            $table->save($choiceEntity);
            ++$pos;
        }

        return new JsonModel(['status' => 'ok']);
    }

}
