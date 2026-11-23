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
use Monarc\FrontOffice\Validator\InputValidator\Record\RecordDuplicateInputValidator;

class ApiAnrRecordDuplicateController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;

    public function __construct(
        private AnrRecordService $anrRecordService,
        private RecordDuplicateInputValidator $recordDuplicateInputValidator
    ) {
    }

    public function create($data)
    {
        $this->validatePostParams($this->recordDuplicateInputValidator, $data);
        $data = $this->recordDuplicateInputValidator->getValidData();
        $id = $this->anrRecordService->duplicate(
            $this->getRequest()->getAttribute('anr'),
            (int)$data['record'],
            $data['label'] ?? null
        );

        return $this->getSuccessfulJsonResponse(['id' => $id]);
    }
}
