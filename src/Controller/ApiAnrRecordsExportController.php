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
use Monarc\FrontOffice\Service\AnrRecordService;

class ApiAnrRecordsExportController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;

    public function __construct(private AnrRecordService $anrRecordService)
    {
    }

    public function create($data)
    {
        if (!empty($data['id'])) {
            $output = $this->anrRecordService->export($data);

            if (empty($data['password'])) {
                $contentType = 'application/json; charset=utf-8';
                $extension = '.json';
            } else {
                $contentType = 'text/plain; charset=utf-8';
                $extension = '.bin';
            }

            $this->getResponse()
                 ->getHeaders()
                 ->clearHeaders()
                 ->addHeaderLine('Content-Type', $contentType)
                 ->addHeaderLine('Content-Disposition', 'attachment; filename="' .
                                  (empty($data['filename']) ? $data['id'] : $data['filename']) . $extension . '"');

            $this->getResponse()
                 ->setContent($output);

            return $this->getResponse();
        }

        if ($data['export'] === "All") {
            if (empty($data['password'])) {
                $contentType = 'application/json; charset=utf-8';
                $extension = '.json';
            } else {
                $contentType = 'text/plain; charset=utf-8';
                $extension = '.bin';
            }
            $anrId = (int)$this->params()->fromRoute('anrid');
            if (empty($anrId)) {
                throw new Exception('Anr id missing', 412);
            }
            $data['anr'] = $anrId;
            $data['filename'] = "records_list";
            $output = $this->anrRecordService->exportAll($data);

            $this->getResponse()
                 ->getHeaders()
                 ->clearHeaders()
                 ->addHeaderLine('Content-Type', $contentType)
                 ->addHeaderLine('Content-Disposition', 'attachment; filename="' .
                                  (empty($data['filename']) ? $data['id'] : $data['filename']) . $extension . '"');

            $this->getResponse()->setContent($output);

            return $this->getResponse();
        }

        throw new Exception('Record to export is required', 412);
    }
}
