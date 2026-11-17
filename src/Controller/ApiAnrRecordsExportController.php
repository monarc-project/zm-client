<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2026 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\Core\Controller\Handler\AbstractRestfulControllerRequestHandler;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\Core\Exception\Exception;
use Monarc\FrontOffice\Export\Controller\Traits\ExportResponseControllerTrait;
use Monarc\FrontOffice\Export\Service\RecordExportService;

class ApiAnrRecordsExportController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;
    use ExportResponseControllerTrait;

    public function __construct(
        private RecordExportService $recordExportService
    ) {
    }

    public function create($data)
    {
        $data['id'] = $this->params()->fromRoute('id') ?? $data['id'] ?? null;
        if (empty($data['id']) && ($data['export'] ?? '') !== 'All') {
            throw new Exception('Record to export is required', 412);
        }

        [$filename, $output] = $this->recordExportService->export(
            $this->getRequest()->getAttribute('anr'),
            empty($data['id']) ? null : (int)$data['id'],
            $data['password'] ?? ''
        );

        return $this->prepareJsonExportResponse($filename, $output, !empty($data['password']));
    }
}
