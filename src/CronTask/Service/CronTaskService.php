<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2022 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\CronTask\Service;

use Monarc\Core\Model\Entity\UserSuperClass;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\CronTask\Table\CronTaskTable;
use Monarc\FrontOffice\Model\Entity\CronTask;

class CronTaskService
{
    private CronTaskTable $cronTaskTable;

    private UserSuperClass $connectedUser;

    public function __construct(CronTaskTable $cronTaskTable, ConnectedUserService $connectedUserService)
    {
        $this->cronTaskTable = $cronTaskTable;
        $this->connectedUser = $connectedUserService->getConnectedUser();
    }

    public function createTask(string $name, array $params = [], int $priority = CronTask::PRIORITY_LOW): CronTask
    {
        $cronTask = (new CronTask($name, $params, $priority))
            ->setCreator($this->connectedUser->getEmail());

        $this->cronTaskTable->save($cronTask);

        return $cronTask;
    }

    public function getNextTaskByName(string $name): ?CronTask
    {
        return $this->cronTaskTable->findNewOneByNameWithHigherPriority($name);
    }

    public function setInProgress(CronTask $cronTask): void
    {
        $this->cronTaskTable->save($cronTask->setStatus(CronTask::STATUS_IN_PROGRESS));
    }

    public function setFailure(CronTask $cronTask, $errorMessage): void
    {
        $this->cronTaskTable->save($cronTask->setStatus(CronTask::STATUS_FAILURE)->setErrorMessage($errorMessage));
    }

    public function setSuccessful(CronTask $cronTask, string $message): void
    {
        $this->cronTaskTable->save($cronTask->setStatus(CronTask::STATUS_DONE)->setSuccessfulMessage($message));
    }
}
