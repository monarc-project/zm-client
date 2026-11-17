<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2026 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\Core\Controller\Handler\AbstractRestfulControllerRequestHandler;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\FrontOffice\InputFormatter\Question\GetQuestionsInputFormatter;
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
        $formattedParams = $this->getFormattedInputParams($this->getQuestionsInputFormatter);

        return $this->getPreparedJsonResponse([
            'count' => $this->anrQuestionService->getCount($formattedParams),
            'questions' => $this->anrQuestionService->getList($formattedParams),
        ]);
    }

    public function get($id)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        return $this->getPreparedJsonResponse($this->anrQuestionService->getQuestionData($anr, (int)$id));
    }

    public function create($data)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');
        $this->validatePostParams($this->questionDataInputValidator, $data);
        $validData = $this->questionDataInputValidator->getValidData();

        return $this->getSuccessfulJsonResponse([
            'id' => $this->anrQuestionService->create($anr, $validData)->getId(),
        ]);
    }

    public function update($id, $data)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');
        $this->validatePostParams($this->questionDataInputValidator, $data);
        $this->anrQuestionService->update($anr, (int)$id, $this->questionDataInputValidator->getValidData());

        return $this->getSuccessfulJsonResponse();
    }

    public function patch($id, $data)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');
        $this->validatePostParams($this->questionDataInputValidator, $data, partial: true);
        $this->anrQuestionService->update($anr, (int)$id, $this->questionDataInputValidator->getValidData());

        return $this->getSuccessfulJsonResponse();
    }

    public function delete($id)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');
        $this->anrQuestionService->delete($anr, (int)$id);

        return $this->getSuccessfulJsonResponse();
    }
}
