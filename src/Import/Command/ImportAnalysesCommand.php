<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2022 Luxembourg House of Cybersecurity LHL.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Import\Command;

use Monarc\Core\Model\Entity\AnrSuperClass;
use Monarc\FrontOffice\CronTask\Service\CronTaskService;
use Monarc\FrontOffice\Import\Service\InstanceImportService;
use Monarc\FrontOffice\Model\Entity\CronTask;
use Monarc\FrontOffice\Model\Table\AnrTable;
use Monarc\FrontOffice\Service\SnapshotService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * The command executes all the pending analysis imports.
 * The first CronTask record with name "instance-import", status "new"(0) and highest priority will be taken.
 * The process ID is stored in the database (CronTask record) to allow termination of the import process if needed.
 */
class ImportAnalysesCommand extends Command
{
    protected static $defaultName = 'monarc:import-analyses';

    private CronTaskService $cronTaskService;

    private AnrTable $anrTable;

    private InstanceImportService $instanceImportService;

    private SnapshotService $snapshotService;

    public function __construct(
        CronTaskService $cronTaskService,
        InstanceImportService $instanceImportService,
        AnrTable $anrTable,
        SnapshotService $snapshotService
    ) {
        $this->cronTaskService = $cronTaskService;
        $this->anrTable = $anrTable;
        $this->instanceImportService = $instanceImportService;
        $this->snapshotService = $snapshotService;

        parent::__construct();
    }


    protected function execute(InputInterface $input, OutputInterface $output)
    {
        while ($cronTask = $this->cronTaskService->getNextTaskByName(CronTask::NAME_INSTANCE_IMPORT)) {
            /* Set status to in process immediately that other executions don't fetch it. */
            $this->cronTaskService->setInProgress($cronTask, getmypid() ?: 0);

            $params = $cronTask->getParams();
            $anrId = $params['anrId'] ?? null;
            if ($anrId === null) {
                $this->cronTaskService
                    ->setFailure($cronTask, 'A mandatory parameter "anrId" is missing in the cron task.');

                return 1;
            }

            try {
                $anr = $this->anrTable->findById((int)$anrId);
                if ($anr->getStatus() !== AnrSuperClass::STATUS_AWAITING_OF_IMPORT) {
                    $this->cronTaskService->setFailure($cronTask, sprintf(
                        'The analysis status "%d" is not correct to start the import process.',
                        $anr->getStatus()
                    ));

                    return 1;
                }
            } catch (\Throwable $e) {
                $this->cronTaskService->setFailure($cronTask, $e->getMessage());

                return 1;
            }

            $password = null;
            if ($params['password'] !== '') {
                $password = base64_decode($params['password']);
            }

            $this->anrTable->saveEntity($anr->setStatus(AnrSuperClass::STATUS_UNDER_IMPORT));
            $ids = [];
            $errors = [];
            try {
                /* Create a Snapshot as a backup. */
                $this->snapshotService->create(['anr' => $anr, 'comment' => 'Import Backup #' . time()]);
                [$ids, $errors] = $this->instanceImportService->importFromFile($anrId, [
                    'file' => [[
                        'tmp_name' => $params['fileNameWithPath'],
                        'error' => UPLOAD_ERR_OK,
                    ]],
                    'password' => $password,
                    'mode' => $params['mode'] ?? null,
                    'idparent' => $params['idparent'] ?? null,
                ]);
            } catch (\Throwable $e) {
                $errors[] = $e->getMessage();
                $errors[] = 'Error Trace:';
                $errors[] = $e->getTraceAsString();
            }

            if (!empty($errors)) {
                $this->cronTaskService->setFailure($cronTask, implode("\n", $errors));
                $this->anrTable->saveEntity($anr->setStatus(AnrSuperClass::STATUS_IMPORT_ERROR));

                return 1;
            }

            $this->anrTable->saveEntity($anr->setStatus(AnrSuperClass::STATUS_ACTIVE));
            $this->cronTaskService->setSuccessful(
                $cronTask,
                'The Analysis was successfully imported with anr ID ' . $anrId
                . ' and root instance IDs: ' . implode(', ', $ids)
            );

            unlink($params['fileNameWithPath']);
        }

        return 0;
    }
}
