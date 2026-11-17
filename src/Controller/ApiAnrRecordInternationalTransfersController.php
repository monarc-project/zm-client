<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2026 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\Core\Controller\Handler\AbstractRestfulControllerRequestHandler;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\FrontOffice\Service\AnrRecordInternationalTransferService;
use Monarc\FrontOffice\InputFormatter\Record\GetRecordInternationalTransfersInputFormatter;
use Monarc\FrontOffice\Validator\InputValidator\Record\RecordInternationalTransferDataInputValidator;

class ApiAnrRecordInternationalTransfersController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;

    public function __construct(
        private GetRecordInternationalTransfersInputFormatter $getRecordInternationalTransfersInputFormatter,
        private RecordInternationalTransferDataInputValidator $recordInternationalTransferDataInputValidator,
        private AnrRecordInternationalTransferService $anrRecordInternationalTransferService
    ) {
    }

    public function getList()
    {
        $params = $this->getFormattedInputParams($this->getRecordInternationalTransfersInputFormatter);

        return $this->getPreparedJsonResponse([
            'count' => $this->anrRecordInternationalTransferService->getCount($params),
            'record-international-transfers' => $this->anrRecordInternationalTransferService->getList($params),
        ]);
    }

    public function get($id)
    {
        return $this->getPreparedJsonResponse(
            $this->anrRecordInternationalTransferService->getRecordInternationalTransferData(
                $this->getRequest()->getAttribute('anr'),
                (int)$id
            )
        );
    }

    public function create($data)
    {
        $this->validatePostParams($this->recordInternationalTransferDataInputValidator, $data);
        $data = $this->recordInternationalTransferDataInputValidator->getValidData();
        $recordInternationalTransfer = $this->anrRecordInternationalTransferService->create(
            $this->getRequest()->getAttribute('anr'),
            $data
        );

        return $this->getSuccessfulJsonResponse(['id' => $recordInternationalTransfer->getId()]);
    }

    public function update($id, $data)
    {
        $this->validatePostParams($this->recordInternationalTransferDataInputValidator, $data);
        $data = $this->recordInternationalTransferDataInputValidator->getValidData();
        $this->anrRecordInternationalTransferService->update(
            $this->getRequest()->getAttribute('anr'),
            (int)$id,
            $data
        );

        return $this->getSuccessfulJsonResponse();
    }
}
