<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2026 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\Core\Controller\Handler\AbstractRestfulControllerRequestHandler;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\FrontOffice\Service\AnrRecordPersonalDataService;
use Monarc\FrontOffice\InputFormatter\Record\GetRecordPersonalDataInputFormatter;
use Monarc\FrontOffice\Validator\InputValidator\Record\RecordPersonalDataDataInputValidator;

class ApiAnrRecordPersonalDataController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;

    public function __construct(
        private GetRecordPersonalDataInputFormatter $getRecordPersonalDataInputFormatter,
        private RecordPersonalDataDataInputValidator $recordPersonalDataDataInputValidator,
        private AnrRecordPersonalDataService $anrRecordPersonalDataService
    ) {
    }

    public function getList()
    {
        $params = $this->getFormattedInputParams($this->getRecordPersonalDataInputFormatter);

        return $this->getPreparedJsonResponse([
            'count' => $this->anrRecordPersonalDataService->getCount($params),
            'record-personal-data' => $this->anrRecordPersonalDataService->getList($params),
        ]);
    }

    public function get($id)
    {
        return $this->getPreparedJsonResponse($this->anrRecordPersonalDataService->getRecordPersonalDataData(
            $this->getRequest()->getAttribute('anr'),
            (int)$id
        ));
    }

    public function create($data)
    {
        $this->validatePostParams($this->recordPersonalDataDataInputValidator, $data);
        $data = $this->recordPersonalDataDataInputValidator->getValidData();
        $recordPersonalData = $this->anrRecordPersonalDataService->create(
            $this->getRequest()->getAttribute('anr'),
            $data
        );

        return $this->getPreparedJsonResponse(['id' => $recordPersonalData->getId()]);
    }

    public function update($id, $data)
    {
        $this->validatePostParams($this->recordPersonalDataDataInputValidator, $data);
        $data = $this->recordPersonalDataDataInputValidator->getValidData();
        $this->anrRecordPersonalDataService->update($this->getRequest()->getAttribute('anr'), (int)$id, $data);

        return $this->getSuccessfulJsonResponse();
    }
}
