<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\Core\Controller\Handler\AbstractRestfulControllerRequestHandler;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\Core\Exception\Exception;
use Monarc\FrontOffice\Service\AnrRecordService;

class ApiAnrRecordDuplicateController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;

    public function __construct(private AnrRecordService $anrRecordService)
    {
    }

    public function create($data)
    {
        if (!isset($data['record'])) {
            throw new Exception('Record missing', 412);
        }

        $id = $this->anrRecordService->duplicateRecord((int)$data['record'], $data['label']);

        return $this->getSuccessfulJsonResponse(['id' => $id]);
    }
}
