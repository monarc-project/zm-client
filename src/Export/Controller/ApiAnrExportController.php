<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2023 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Export\Controller;

use Monarc\Core\Controller\Handler\AbstractRestfulControllerRequestHandler;
use Monarc\FrontOffice\Export\Service\AnrExportService;
use Monarc\FrontOffice\Entity\Anr;
use function strlen;

class ApiAnrExportController extends AbstractRestfulControllerRequestHandler
{
    private AnrExportService $anrExportService;

    public function __construct(AnrExportService $anrExportService)
    {
        $this->anrExportService = $anrExportService;
    }

    /**
     * @param array $data
     */
    public function create($data)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        [$fileName, $output] = $this->anrExportService->export($anr, $data);

        $contentType = 'application/json; charset=utf-8';
        $extension = '.json';
        if (!empty($data['password'])) {
            $contentType = 'text/plain; charset=utf-8';
            $extension = '.bin';
        }

        $response = $this->getResponse();
        $response->setContent($output);

        $headers = $response->getHeaders();
        $filename = empty($fileName) ? $anr->getId() : $fileName;
        $headers->clearHeaders()
            ->addHeaderLine('Content-Type', $contentType)
            ->addHeaderLine('Content-Length', strlen($output))
            ->addHeaderLine('Content-Disposition', 'attachment; filename="' . $filename . $extension);

        return $response;
    }
}
