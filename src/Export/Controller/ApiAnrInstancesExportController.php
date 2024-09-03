<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Export\Controller;

use Monarc\Core\Controller\Handler\AbstractRestfulControllerRequestHandler;
use Monarc\FrontOffice\Export\Controller\Traits\ExportResponseControllerTrait;
use Monarc\FrontOffice\Export\Service\InstanceExportService;
use Monarc\FrontOffice\Entity\Anr;

class ApiAnrInstancesExportController extends AbstractRestfulControllerRequestHandler
{
    use ExportResponseControllerTrait;

    public function __construct(private InstanceExportService $anrInstanceExportService)
    {
    }

    public function create($data)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');
        $result = $this->anrInstanceExportService->export($anr, $data);

        return $this->prepareJsonExportResponse($result['filename'], $result['content'], !empty($data['password']));
    }
}
