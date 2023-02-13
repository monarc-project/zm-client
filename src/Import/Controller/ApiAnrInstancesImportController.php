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
use Monarc\Core\Model\Entity\AnrSuperClass;
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

    /**
     * Returns all the result messages logs of imports of the analysis.
     */
    public function getList()
    {
        $anrId = (int)$this->params()->fromRoute('anrid');
        $anr = $this->anrTable->findById($anrId);

        return new JsonModel([
            'status' => $anr->getStatusName(),
            'messages' => $this->cronTaskService->getResultMessagesByNameWithParam(
                CronTask::NAME_INSTANCE_IMPORT,
                ['anrId' => $anrId]
            ),
        ]);
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
            $fileNameWithPath = $this->moveTmpFile($tmpFile, $this->importConfig['uploadFolder'], $fileName);

            /* Prepare the import params. */
            $password = '';
            if (!empty($data['password'])) {
                $password = base64_encode($data['password']);
            }
            $mode = $data['mode'] ?? 'merge';
            $idparent = $data['idparent'] ?? 0;

            /* Create a job for the process */
            $this->cronTaskService->createTask(
                CronTask::NAME_INSTANCE_IMPORT,
                compact('anrId', 'fileNameWithPath', 'password', 'mode', 'idparent'),
                CronTask::PRIORITY_HIGH
            );

            /* Set Anr status to pending. */
            $this->anrTable->saveEntity(
                $this->anrTable->findById($anrId)->setStatus(AnrSuperClass::STATUS_AWAITING_OF_IMPORT)
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

    /**
     * Terminates background import process if an anr is under import.
     *
     * @return JsonModel
     */
    public function deleteList($data)
    {
        $anrId = (int)$this->params()->fromRoute('anrid');
        if (empty($anrId)) {
            throw new Exception('Anr id missing', 412);
        }

        if (!empty($this->importConfig['isBackgroundProcessActive'])) {
            $importCronTask = $this->cronTaskService
                ->getLatestTaskByNameWithParam(CronTask::NAME_INSTANCE_IMPORT, ['anrId' => $anrId]);
            $anr = $this->anrTable->findById($anrId);
            if ($importCronTask !== null && !$anr->isActive()) {
                $anr->setStatus(AnrSuperClass::STATUS_ACTIVE);
                $this->anrTable->saveEntity($anr, false);
                $this->cronTaskService->terminateCronTask($importCronTask);
            }
        }

        return new JsonModel(['status' => 'ok']);
    }
}
