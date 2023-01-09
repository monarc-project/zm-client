<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2022 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Import\Controller;

use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\View\Model\JsonModel;
use Monarc\Core\Exception\Exception;
use Monarc\Core\Service\ConfigService;
use Monarc\FrontOffice\CronTask\Service\CronTaskService;
use Monarc\FrontOffice\Helper\FileUploadHelperTrait;
use Monarc\FrontOffice\Import\Service\InstanceImportService;
use Monarc\FrontOffice\Model\Entity\CronTask;
use Monarc\FrontOffice\Model\Table\AnrTable;

class ApiAnrInstancesImportController extends AbstractRestfulController
{
    use FileUploadHelperTrait;

    private InstanceImportService $instanceImportService;

    private CronTaskService $cronTaskService;

    private AnrTable $anrTable;

    private array $importConfig;

    public function __construct(
        InstanceImportService $instanceImportService,
        ConfigService $configService,
        CronTaskService $cronTaskService,
        AnrTable $anrTable
    ) {
        $this->instanceImportService = $instanceImportService;
        $this->importConfig = $configService->getConfigOption('import') ? : [];
        $this->cronTaskService = $cronTaskService;
        $this->anrTable = $anrTable;
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

        if (!empty($this->importConfig['isBackgroundProcessActive'])) {
            /* Upload file to process it later. */
            $tmpFile = current($files);
            $fileName = $anrId . '-' . $tmpFile['name'];
            $fileNameWithPath = $this->moveTmpFile(current($files), $this->importConfig['uploadFolder'], $fileName);

            $password = '';
            if (!empty($data['password'])) {
                $password = base64_encode($data['password']);
            }
            $mode = $data['mode'] ?: 'merge';
            $idparent = $data['idparent'] ?: 0;

            /* Create a job for the process */
            $this->cronTaskService->createTask(
                CronTask::NAME_INSTANCE_IMPORT,
                compact('anrId', 'fileNameWithPath', 'password', 'mode', 'idparent'),
                CronTask::PRIORITY_HIGH
            );

            return new JsonModel([
                'status' => 'ok',
                'isBackgroundProcess' => true,
                'id' => [],
                'errors' => [],
            ]);
        }

        [$ids, $errors] = $this->instanceImportService->importFromFile($anrId, $data);

        return new JsonModel([
            'status' => 'ok',
            'isBackgroundProcess' => false,
            'id' => $ids,
            'errors' => $errors,
        ]);
    }
}
