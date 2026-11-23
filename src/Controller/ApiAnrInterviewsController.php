<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2026 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\Core\Controller\Handler\AbstractRestfulControllerRequestHandler;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\InputFormatter\Interview\GetInterviewsInputFormatter;
use Monarc\FrontOffice\Service\AnrInterviewService;
use Monarc\FrontOffice\Validator\InputValidator\Interview\InterviewDataInputValidator;

class ApiAnrInterviewsController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;

    public function __construct(
        private GetInterviewsInputFormatter $getInterviewsInputFormatter,
        private InterviewDataInputValidator $interviewDataInputValidator,
        private AnrInterviewService $anrInterviewService
    ) {
    }

    public function getList()
    {
        $params = $this->getFormattedInputParams($this->getInterviewsInputFormatter);

        return $this->getPreparedJsonResponse([
            'count' => $this->anrInterviewService->getCount($params),
            'interviews' => $this->anrInterviewService->getList($params),
        ]);
    }

    public function get($id)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        return $this->getPreparedJsonResponse($this->anrInterviewService->getInterviewData($anr, (int)$id));
    }

    public function create($data)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');
        $this->validatePostParams($this->interviewDataInputValidator, $data);
        $interview = $this->anrInterviewService->create($anr, $this->interviewDataInputValidator->getValidData());

        return $this->getSuccessfulJsonResponse(['id' => $interview->getId()]);
    }

    public function update($id, $data)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');
        $this->validatePostParams($this->interviewDataInputValidator, $data);
        $this->anrInterviewService->update($anr, (int)$id, $this->interviewDataInputValidator->getValidData());

        return $this->getSuccessfulJsonResponse();
    }

    public function patch($id, $data)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');
        $this->validatePostParams($this->interviewDataInputValidator, $data, partial: true);
        $this->anrInterviewService->update($anr, (int)$id, $this->interviewDataInputValidator->getValidData());

        return $this->getSuccessfulJsonResponse();
    }

    public function delete($id)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');
        $this->anrInterviewService->delete($anr, (int)$id);

        return $this->getSuccessfulJsonResponse();
    }
}
