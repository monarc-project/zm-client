<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2022 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\CronTask\Service;

use DateTime;
use Monarc\Core\Model\Entity\UserSuperClass;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\CronTask\Table\CronTaskTable;
use Monarc\FrontOffice\Model\Entity\CronTask;

class CronTaskService
{
    private CronTaskTable $cronTaskTable;

    private ?UserSuperClass $connectedUser;

    public function __construct(CronTaskTable $cronTaskTable, ConnectedUserService $connectedUserService)
    {
        $this->cronTaskTable = $cronTaskTable;
        if ($connectedUserService->getConnectedUser() !== null) {
            $this->connectedUser = $connectedUserService->getConnectedUser();
        }
    }

    public function createTask(string $name, array $params = [], int $priority = CronTask::PRIORITY_LOW): CronTask
    {
        $cronTask = (new CronTask($name, $params, $priority))
            ->setCreator($this->connectedUser !== null ? $this->connectedUser->getEmail() : 'System');

        $this->cronTaskTable->save($cronTask);

        return $cronTask;
    }

    public function getNextTaskByName(string $name): ?CronTask
    {
        return $this->cronTaskTable->findNewOneByNameWithHigherPriority($name);
    }

    public function getLatestTaskByNameWithParam(string $name, array $searchParam): ?CronTask
    {
        $searchParamKey = (string)array_key_first($searchParam);
        if ($searchParamKey === '') {
            return null;
        }

        $searchParamValue = current($searchParam);
        $dateTimeFrom = new DateTime('-1 day');
        $cronTasks = $this->cronTaskTable->findByNameOrderedByExecutionOrderLimitedByDate($name, $dateTimeFrom);
        foreach ($cronTasks as $cronTask) {
            $params = $cronTask->getParams();
            if (\array_key_exists($searchParamKey, $params) && $params[$searchParamKey] === $searchParamValue) {
                return $cronTask;
            }
        }

        return null;
    }

    public function getResultMessagesByNameWithParam(string $name, array $searchParam): array
    {
        $searchParamKey = (string)array_key_first($searchParam);
        if ($searchParamKey === '') {
            return [];
        }

        $searchParamValue = current($searchParam);
        $dateTimeFrom = new DateTime('-10 days');
        $cronTasks = $this->cronTaskTable->findByNameOrderedByExecutionOrderLimitedByDate($name, $dateTimeFrom);
        $messages = [];
        foreach ($cronTasks as $cronTask) {
            $params = $cronTask->getParams();
            if (\array_key_exists($searchParamKey, $params) && $params[$searchParamKey] === $searchParamValue) {
                $messages[] =  $cronTask->getResultMessage();
            }
        }

        return $messages;
    }

    public function setInProgress(CronTask $cronTask, int $pid): void
    {
        $cronTask
            ->setStatus(CronTask::STATUS_IN_PROGRESS)
            ->setPid($pid)
            ->setUpdater($this->connectedUser !== null ? $this->connectedUser->getEmail() : 'System');

        $this->cronTaskTable->save($cronTask);
    }

    public function setFailure(CronTask $cronTask, string $message): void
    {
        $cronTask
            ->setStatus(CronTask::STATUS_FAILURE)
            ->setResultMessage($message)
            ->setUpdater($this->connectedUser !== null ? $this->connectedUser->getEmail() : 'System');

        $this->cronTaskTable->save($cronTask);
    }

    public function setSuccessful(CronTask $cronTask, string $message): void
    {
        $cronTask
            ->setStatus(CronTask::STATUS_DONE)
            ->setResultMessage($message)
            ->setUpdater($this->connectedUser !== null ? $this->connectedUser->getEmail() : 'System');

        $this->cronTaskTable->save($cronTask);
    }

    public function terminateCronTask(CronTask $cronTask): void
    {
        if ($cronTask->getStatus() === CronTask::STATUS_IN_PROGRESS && !empty($cronTask->getPid())) {
            if (posix_kill($cronTask->getPid(), 9)) {
                $cronTask->setStatus(CronTask::STATUS_TERMINATED);
            }
        } elseif ($cronTask->getStatus() === CronTask::STATUS_NEW) {
            $cronTask->setStatus(CronTask::STATUS_TERMINATED);
        }

        $this->cronTaskTable->save($cronTask);
    }
}
