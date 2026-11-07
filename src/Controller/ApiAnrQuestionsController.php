<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2026 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\Core\Controller\Handler\AbstractRestfulControllerRequestHandler;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\Core\InputFormatter\Question\GetQuestionsInputFormatter;
use Monarc\FrontOffice\Validator\InputValidator\Question\QuestionDataInputValidator;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Service\AnrQuestionService;

class ApiAnrQuestionsController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;

    public function __construct(
        private GetQuestionsInputFormatter $getQuestionsInputFormatter,
        private QuestionDataInputValidator $questionDataInputValidator,
        private AnrQuestionService $anrQuestionService
    ) {
    }

    public function getList()
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        $formattedParams = $this->getFormattedInputParams($this->getQuestionsInputFormatter);

        return $this->getPreparedJsonResponse([
            'count' => $this->anrQuestionService->getCount($formattedParams, $anr),
            'questions' => $this->anrQuestionService->getList($formattedParams, $anr),
        ]);
    }

    public function get($id)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        $id = $this->questionDataInputValidator->validateIds([$id])[0];

        return $this->getPreparedJsonResponse($this->anrQuestionService->getEntity($id, $anr));
    }

    public function create($data)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        $validData = $this->questionDataInputValidator->validate($data);
        $id = $this->anrQuestionService->create($validData, $anr);

        return $this->getSuccessfulJsonResponse(['id' => $id]);
    }

    public function update($id, $data)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        $id = $this->questionDataInputValidator->validateIds([$id])[0];
        $validData = $this->questionDataInputValidator->validate($data);
        $this->anrQuestionService->update($id, $validData, $anr);

        return $this->getSuccessfulJsonResponse();
    }

    public function patch($id, $data)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        $id = $this->questionDataInputValidator->validateIds([$id])[0];
        $validData = $this->questionDataInputValidator->validate($data, true);
        $this->anrQuestionService->update($id, $validData, $anr);

        return $this->getSuccessfulJsonResponse();
    }

    public function delete($id)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        $id = $this->questionDataInputValidator->validateIds([$id])[0];
        $this->anrQuestionService->delete($id, $anr);

        return $this->getSuccessfulJsonResponse();
    }

    public function deleteList($data)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        $ids = $this->questionDataInputValidator->validateIds($data);
        $this->anrQuestionService->deleteList($ids, $anr);

        return $this->getSuccessfulJsonResponse();
    }
}
