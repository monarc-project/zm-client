<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2026 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\Core\Controller\Handler\AbstractRestfulControllerRequestHandler;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\FrontOffice\Import\Service\RecordImportService;

class ApiAnrRecordsImportController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;

    public function __construct(
        private RecordImportService $recordImportService
    ) {
    }

    public function create($data)
    {
        $data['file'] = $this->params()->fromFiles('file');
        [$ids, $errors] = $this->recordImportService->importFromFile($this->getRequest()->getAttribute('anr'), $data);

        return $this->getSuccessfulJsonResponse(['id' => $ids, 'errors' => $errors]);
    }
}
