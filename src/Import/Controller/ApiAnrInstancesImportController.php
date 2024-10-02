<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Import\Controller;

use Laminas\View\Model\JsonModel;
use Monarc\Core\Controller\Handler\AbstractRestfulControllerRequestHandler;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\Core\Exception\Exception;
use Monarc\Core\Helper\FileUploadHelperTrait;
use Monarc\Core\Entity\AnrSuperClass;
use Monarc\Core\Service\ConfigService;
use Monarc\FrontOffice\CronTask\Service\CronTaskService;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Import\Service\InstanceImportService;
use Monarc\FrontOffice\Entity\CronTask;
use Monarc\FrontOffice\Table\AnrTable;

class ApiAnrInstancesImportController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;
    use FileUploadHelperTrait;

    private array $importConfig;

    public function __construct(
        private InstanceImportService $instanceImportService,
        private CronTaskService $cronTaskService,
        private AnrTable $anrTable,
        ConfigService $configService
    ) {
        $this->importConfig = $configService->getConfigOption('import') ? : [];
    }

    /**
     * Returns all the result messages logs of imports of the analysis.
     */
    public function getList()
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        return $this->getPreparedJsonResponse([
            'status' => $anr->getStatusName(),
            'messages' => $this->cronTaskService->getResultMessagesByNameWithParam(
                CronTask::NAME_INSTANCE_IMPORT,
                ['anrId' => $anr->getId()]
            ),
        ]);
    }

    public function create($data)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');
        $files = $this->params()->fromFiles('file');
        if (empty($files)) {
            throw new Exception('File missing', 412);
        }
        $data['file'] = $files;

        if (!empty($this->importConfig['isBackgroundProcessActive'])) {
            // TODO: move it to a service
            /* Upload file to process it later. */
            $anrId = $anr->getId();
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
            $this->anrTable->save($anr->setStatus(AnrSuperClass::STATUS_AWAITING_OF_IMPORT));

            return $this->getSuccessfulJsonResponse([
                'isBackgroundProcess' => true,
                'id' => [],
                'errors' => [],
            ]);
        }

        [$ids, $errors] = $this->instanceImportService->importFromFile($anr, $data);

        return $this->getSuccessfulJsonResponse([
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
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        if (!empty($this->importConfig['isBackgroundProcessActive'])) {
            $importCronTask = $this->cronTaskService
                ->getLatestTaskByNameWithParam(CronTask::NAME_INSTANCE_IMPORT, ['anrId' => $anr->getId()]);
            if ($importCronTask !== null && !$anr->isActive()) {
                $anr->setStatus(AnrSuperClass::STATUS_ACTIVE);
                $this->anrTable->save($anr, false);
                $this->cronTaskService->terminateCronTask($importCronTask);
            }
        }

        return $this->getSuccessfulJsonResponse();
    }
}
