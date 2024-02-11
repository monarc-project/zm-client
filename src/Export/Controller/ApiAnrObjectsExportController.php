<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2023 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Export\Controller;

use Monarc\Core\Controller\Handler\AbstractRestfulControllerRequestHandler;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\FrontOffice\Model\Entity\Anr;
use Monarc\FrontOffice\Export\Service\AnrObjectExportService;

class ApiAnrObjectsExportController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;

    private AnrObjectExportService $anrObjectExportService;

    public function __construct(AnrObjectExportService $anrObjectExportService)
    {
        $this->anrObjectExportService = $anrObjectExportService;
    }

    /**
     * @param array $data
     */
    public function create($data)
    {
        // TODO: add a validator.
        if (empty($data['id'])) {
            throw new \Monarc\Core\Exception\Exception('Object to export is required', 412);
        }

        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        // TODO: ...
        $data['anr'] = $anr->getId();
        $output = $this->anrObjectExportService->export($anr, $data);

        $response = $this->getResponse();
        $response->setContent($output);

        $headers = $response->getHeaders();
        $filename = empty($data['filename']) ? $data['id'] : $data['filename'];
        $headers->clearHeaders()
            ->addHeaderLine('Content-Type', 'application/json; charset=utf-8')
            ->addHeaderLine('Content-Disposition', 'attachment; filename="' . $filename . '.json"');

        return $response;
    }
}
