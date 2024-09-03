<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\Core\Controller\Handler\AbstractRestfulControllerRequestHandler;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\Core\Exception\Exception;
use Monarc\FrontOffice\Export\Controller\Traits\ExportResponseControllerTrait;
use Monarc\FrontOffice\Service\AnrRecordService;

class ApiAnrRecordsExportController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;
    use ExportResponseControllerTrait;

    public function __construct(private AnrRecordService $anrRecordService)
    {
    }

    public function create($data)
    {
        if (!empty($data['id'])) {
            $output = $this->anrRecordService->export($data);
            $filename = empty($data['filename']) ? $data['id'] : $data['filename'];

            return $this->prepareJsonExportResponse($filename, $output, !empty($data['password']));
        }

        if ($data['export'] === "All") {
            $anrId = (int)$this->params()->fromRoute('anrid');
            if (empty($anrId)) {
                throw new Exception('Anr id missing', 412);
            }
            $data['anr'] = $anrId;
            $output = $this->anrRecordService->exportAll($data);

            return $this->prepareJsonExportResponse('records_list', $output, !empty($data['password']));
        }

        throw new Exception('Record to export is required', 412);
    }
}
