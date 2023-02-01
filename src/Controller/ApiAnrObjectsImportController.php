<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2022 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\View\Model\JsonModel;
use Monarc\Core\Exception\Exception;
use Monarc\FrontOffice\Service\AnrObjectService;

class ApiAnrObjectsImportController extends AbstractRestfulController
{
    private AnrObjectService $anrObjectService;

    public function __construct(AnrObjectService $anrObjectService)
    {
        $this->anrObjectService = $anrObjectService;
    }

    public function getList()
    {
        $anrId = (int)$this->params()->fromRoute('anrid');
        $filter = $this->params()->fromQuery("filter");
        if (empty($anrId)) {
            throw new Exception('Anr id missing', 412);
        }

        $objects = $this->anrObjectService->getCommonObjects($anrId, $filter);

        return new JsonModel([
            'count' => \count($objects),
            'objects' => $objects,
        ]);
    }

    public function get($id)
    {
        $anrId = (int)$this->params()->fromRoute('anrid');
        if (empty($anrId)) {
            throw new Exception('Anr id missing', 412);
        }

        $object = $this->anrObjectService->getCommonEntity($anrId, $id);

        $this->formatDependencies($object, ['asset', 'category', 'rolfTag']);
        unset($object['anrs']);

        return new JsonModel($object);
    }

    public function create($data)
    {
        $anrId = (int)$this->params()->fromRoute('anrid');
        if (empty($anrId)) {
            throw new Exception('Anr id missing', 412);
        }

        $files = $this->params()->fromFiles('file');
        if (empty($files)) {
            throw new Exception('File missing', 412);
        }
        $data['file'] = $files;

        [$ids, $errors] = $this->anrObjectService->importFromFile($anrId, $data);

        return new JsonModel([
            'status' => 'ok',
            'id' => $ids,
            'errors' => $errors,
        ]);
    }

    public function patch($id, $data)
    {
        $anrId = (int)$this->params()->fromRoute('anrid');
        if (empty($anrId)) {
            throw new Exception('Anr id missing', 412);
        }
        $data['anr'] = $anrId;

        $monarcObject = $this->anrObjectService->importFromCommon($id, $data);
        if ($monarcObject === null) {
            throw new Exception('An error occurred during the import of the object.', 412);
        }

        return new JsonModel([
            'status' => 'ok',
            'id' => $monarcObject->getUuid(),
        ]);
    }
}
