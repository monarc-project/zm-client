<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2026 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\Core\Controller\Handler\AbstractRestfulControllerRequestHandler;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\FrontOffice\Service\AnrRecordService;
use Monarc\FrontOffice\InputFormatter\Record\GetRecordsInputFormatter;
use Monarc\FrontOffice\Validator\InputValidator\Record\RecordDataInputValidator;

class ApiAnrRecordsController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;

    public function __construct(
        private GetRecordsInputFormatter $getRecordsInputFormatter,
        private RecordDataInputValidator $recordDataInputValidator,
        private AnrRecordService $anrRecordService
    ) {
    }

    public function getList()
    {
        $params = $this->getFormattedInputParams($this->getRecordsInputFormatter);

        return $this->getPreparedJsonResponse([
            'count' => $this->anrRecordService->getCount($params),
            'records' => $this->anrRecordService->getList($params),
        ]);
    }

    public function get($id)
    {
        return $this->getPreparedJsonResponse($this->anrRecordService->getRecordData(
            $this->getRequest()->getAttribute('anr'),
            (int)$id
        ));
    }

    public function create($data)
    {
        $this->validatePostParams($this->recordDataInputValidator, $data);
        $data = $this->recordDataInputValidator->getValidData();
        $record = $this->anrRecordService->create($this->getRequest()->getAttribute('anr'), $data);

        return $this->getSuccessfulJsonResponse(['id' => $record->getId()]);
    }

    public function update($id, $data)
    {
        $this->validatePostParams($this->recordDataInputValidator, $data);
        $data = $this->recordDataInputValidator->getValidData();
        $record = $this->anrRecordService->update($this->getRequest()->getAttribute('anr'), (int)$id, $data);

        return $this->getSuccessfulJsonResponse(['updatedAt' => $record->getUpdatedAt()]);
    }

    public function patch($id, $data)
    {
        $this->validatePostParams($this->recordDataInputValidator, $data, partial: true);
        $record = $this->anrRecordService->update(
            $this->getRequest()->getAttribute('anr'),
            (int)$id,
            $this->recordDataInputValidator->getValidData()
        );

        return $this->getSuccessfulJsonResponse(['updatedAt' => $record->getUpdatedAt()]);
    }

    public function delete($id)
    {
        $this->anrRecordService->delete($this->getRequest()->getAttribute('anr'), (int)$id);

        return $this->getSuccessfulJsonResponse();
    }
}
