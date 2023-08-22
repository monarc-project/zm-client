<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2023 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Export\Controller;

use Monarc\Core\Controller\Handler\AbstractRestfulControllerRequestHandler;
use Monarc\FrontOffice\Export\Service\AnrInstanceExportService;
use Monarc\FrontOffice\Model\Entity\Anr;

class ApiAnrInstancesExportController extends AbstractRestfulControllerRequestHandler
{
    private AnrInstanceExportService $anrInstanceExportService;

    public function __construct(AnrInstanceExportService $anrInstanceExportService)
    {
        $this->anrInstanceExportService = $anrInstanceExportService;
    }

    public function create($data)
    {
        // TODO: add the $data validator $data['id'] => instanceId is required.

        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        $output = $this->anrInstanceExportService->export($data);

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
}
