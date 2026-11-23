<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2026 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\Core\Controller\Handler\AbstractRestfulControllerRequestHandler;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\FrontOffice\Service\AnrRecordRecipientService;
use Monarc\FrontOffice\InputFormatter\Record\GetRecordRecipientsInputFormatter;
use Monarc\FrontOffice\Validator\InputValidator\Record\RecordRecipientDataInputValidator;

class ApiAnrRecordRecipientsController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;

    public function __construct(
        private GetRecordRecipientsInputFormatter $getRecordRecipientsInputFormatter,
        private RecordRecipientDataInputValidator $recordRecipientDataInputValidator,
        private AnrRecordRecipientService $anrRecordRecipientService
    ) {
    }

    public function getList()
    {
        $params = $this->getFormattedInputParams($this->getRecordRecipientsInputFormatter);

        return $this->getPreparedJsonResponse([
            'count' => $this->anrRecordRecipientService->getCount($params),
            'record-recipients' => $this->anrRecordRecipientService->getList($params),
        ]);
    }

    public function get($id)
    {
        return $this->getPreparedJsonResponse($this->anrRecordRecipientService->getRecordRecipientData(
            $this->getRequest()->getAttribute('anr'),
            (int)$id
        ));
    }

    public function create($data)
    {
        $this->validatePostParams($this->recordRecipientDataInputValidator, $data);
        $data = $this->recordRecipientDataInputValidator->getValidData();
        $recordRecipient = $this->anrRecordRecipientService->create($this->getRequest()->getAttribute('anr'), $data);

        return $this->getSuccessfulJsonResponse(['id' => $recordRecipient->getId()]);
    }

    public function update($id, $data)
    {
        $this->validatePostParams($this->recordRecipientDataInputValidator, $data);
        $data = $this->recordRecipientDataInputValidator->getValidData();
        $this->anrRecordRecipientService->update($this->getRequest()->getAttribute('anr'), (int)$id, $data);

        return $this->getSuccessfulJsonResponse();
    }
}
