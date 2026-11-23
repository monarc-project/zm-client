<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2026 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\Core\Controller\Handler\AbstractRestfulControllerRequestHandler;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\FrontOffice\Service\AnrRecordProcessorService;
use Monarc\FrontOffice\InputFormatter\Record\GetRecordProcessorsInputFormatter;
use Monarc\FrontOffice\Validator\InputValidator\Record\RecordProcessorDataInputValidator;

class ApiAnrRecordProcessorsController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;

    public function __construct(
        private GetRecordProcessorsInputFormatter $getRecordProcessorsInputFormatter,
        private RecordProcessorDataInputValidator $recordProcessorDataInputValidator,
        private AnrRecordProcessorService $anrRecordProcessorService
    ) {
    }

    public function getList()
    {
        $params = $this->getFormattedInputParams($this->getRecordProcessorsInputFormatter);

        return $this->getPreparedJsonResponse([
            'count' => $this->anrRecordProcessorService->getCount($params),
            'record-processors' => $this->anrRecordProcessorService->getList($params),
        ]);
    }

    public function get($id)
    {
        return $this->getPreparedJsonResponse($this->anrRecordProcessorService->getRecordProcessorData(
            $this->getRequest()->getAttribute('anr'),
            (int)$id
        ));
    }

    public function create($data)
    {
        $this->validatePostParams($this->recordProcessorDataInputValidator, $data);
        $data = $this->recordProcessorDataInputValidator->getValidData();
        $recordProcessor = $this->anrRecordProcessorService->create($this->getRequest()->getAttribute('anr'), $data);

        return $this->getSuccessfulJsonResponse(['id' => $recordProcessor->getId()]);
    }

    public function update($id, $data)
    {
        $this->validatePostParams($this->recordProcessorDataInputValidator, $data);
        $data = $this->recordProcessorDataInputValidator->getValidData();
        $this->anrRecordProcessorService->update($this->getRequest()->getAttribute('anr'), (int)$id, $data);

        return $this->getSuccessfulJsonResponse();
    }
}
