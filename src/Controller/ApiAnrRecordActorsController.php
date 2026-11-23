<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2026 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\Core\Controller\Handler\AbstractRestfulControllerRequestHandler;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\FrontOffice\Service\AnrRecordActorService;
use Monarc\FrontOffice\InputFormatter\Record\GetRecordActorsInputFormatter;
use Monarc\FrontOffice\Validator\InputValidator\Record\RecordActorDataInputValidator;

class ApiAnrRecordActorsController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;

    public function __construct(
        private GetRecordActorsInputFormatter $getRecordActorsInputFormatter,
        private RecordActorDataInputValidator $recordActorDataInputValidator,
        private AnrRecordActorService $anrRecordActorService
    ) {
    }

    public function getList()
    {
        $params = $this->getFormattedInputParams($this->getRecordActorsInputFormatter);

        return $this->getPreparedJsonResponse([
            'count' => $this->anrRecordActorService->getCount($params),
            'record-actors' => $this->anrRecordActorService->getList($params),
        ]);
    }

    public function get($id)
    {
        return $this->getPreparedJsonResponse($this->anrRecordActorService->getRecordActorData(
            $this->getRequest()->getAttribute('anr'),
            (int)$id
        ));
    }

    public function create($data)
    {
        $this->validatePostParams($this->recordActorDataInputValidator, $data);
        $data = $this->recordActorDataInputValidator->getValidData();
        $recordActor = $this->anrRecordActorService->create($this->getRequest()->getAttribute('anr'), $data);

        return $this->getSuccessfulJsonResponse(['id' => $recordActor->getId()]);
    }

    public function update($id, $data)
    {
        $this->validatePostParams($this->recordActorDataInputValidator, $data);
        $data = $this->recordActorDataInputValidator->getValidData();
        $this->anrRecordActorService->update($this->getRequest()->getAttribute('anr'), (int)$id, $data);

        return $this->getSuccessfulJsonResponse();
    }
}
