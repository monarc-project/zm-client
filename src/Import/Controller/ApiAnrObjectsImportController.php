<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Import\Controller;

use Monarc\Core\Controller\Handler\AbstractRestfulControllerRequestHandler;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\Core\Exception\Exception;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Import\Service\ObjectImportService;

class ApiAnrObjectsImportController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;

    public function __construct(private ObjectImportService $objectImportService)
    {
    }

    public function getList()
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');
        $filter = $this->params()->fromQuery('filter', '');

        $objects = $this->objectImportService->getObjectsDataFromCommonDatabase($anr, $filter);

        return $this->getPreparedJsonResponse([
            'count' => \count($objects),
            'objects' => $objects,
        ]);
    }

    public function get($id)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        return $this->getPreparedJsonResponse($this->objectImportService->getObjectDataFromCommonDatabase($anr, $id));
    }

    public function create($data)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        $data['file'] = $this->params()->fromFiles('file');
        if (empty($data['file'])) {
            throw new Exception('File is missing.', 412);
        }

        [$ids, $errors] = $this->objectImportService->importFromFile($anr, $data);

        return $this->getSuccessfulJsonResponse([
            'id' => $ids,
            'errors' => $errors,
        ]);
    }

    public function patch($id, $data)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        $object = $this->objectImportService->importFromCommonDatabase($anr, $id, $data);

        return $this->getSuccessfulJsonResponse(['id' => $object->getUuid()]);
    }
}
