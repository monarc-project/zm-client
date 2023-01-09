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
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

// TODO: Added readme section about this command.
class ImportAnalysesCommand extends Command
{
    protected static $defaultName = 'monarc:import-analyses';

    private CronTaskService $cronTaskService;

    private InstanceImportService $instanceImportService;

    private AnrTable $anrTable;

    public function __construct(
        CronTaskService $cronTaskService,
        InstanceImportService $instanceImportService,
        AnrTable $anrTable
    ) {
        $this->cronTaskService = $cronTaskService;
        $this->anrTable = $anrTable;
        $this->instanceImportService = $instanceImportService;

        parent::__construct();
    }


    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $cronTask = $this->cronTaskService->getNextTaskByName(CronTask::NAME_INSTANCE_IMPORT);
        if ($cronTask === null) {
            return 0;
        }

        $params = $cronTask->getParams();
        $anrId = $params['anrId'] ?? null;
        if ($anrId === null) {
            $this->cronTaskService->setFailure($cronTask, 'A mandatory parameter "anrId" is missing in the cron task.');

            return 1;
        }

        try {
            $anr = $this->anrTable->findById((int)$anrId);
        } catch (\Throwable $e) {
            $this->cronTaskService->setFailure($cronTask, $e->getMessage());

            return 1;
        }

        /* Set statuses for the upcoming process. */
        $this->cronTaskService->setInProgress($cronTask);
        $anr->setStatus(AnrSuperClass::STATUS_UNDER_IMPORT);
        $this->anrTable->saveEntity($anr);

        $password = null;
        if ($params['password'] !== '') {
            $password = base64_decode($params['password']);
        }

        $ids = [];
        try {
            [$ids, $errors] = $this->instanceImportService->importFromFile($anrId, [
                'file' => [
                    'tmp_name' => $params['fileNameWithPath']
                ],
                'password' => $password,
                'mode' => $params['mode'] ?? null,
                'idparent' => $params['idparent'] ?? null,
            ]);
        } catch (\Throwable $e) {
            $errors[] = $e->getMessage();
        }

        if (!empty($errors)) {
            $this->cronTaskService->setFailure($cronTask, implode("\n", $errors));

            return 1;
        }

        $this->cronTaskService->setSuccessful(
            $cronTask,
            'The Analysis was successfully imported with anr ID ' . $anrId
            . ' and root instance IDs: ' . implode(', ', $ids)
        );

        return 0;
    }
}
