<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\View\Model\JsonModel;
use Monarc\Core\Exception\Exception;
use Monarc\FrontOffice\Service\InstanceImportService;

/**
 * Api ANR Instances Import Controller
 *
 * Class ApiAnrInstancesImportController
 * @package Monarc\FrontOffice\Controller
 */
class ApiAnrInstancesImportController extends AbstractRestfulController
{
    /** @var InstanceImportService */
    private $instanceImportService;

    public function __construct(InstanceImportService $instanceImportService)
    {
        $this->instanceImportService = $instanceImportService;
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

        list($ids, $errors) = $this->instanceImportService->importFromFile($anrId, $data);

        return new JsonModel([
            'status' => 'ok',
            'id' => $ids,
            'errors' => $errors,
        ]);
    }
}
