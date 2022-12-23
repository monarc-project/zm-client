<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2022 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\CronTask\Service;

use Monarc\FrontOffice\CronTask\Table\CronTaskTable;
use Monarc\FrontOffice\Model\Entity\CronTask;

class CronTaskService
{
    private CronTaskTable $cronTaskTable;

    public function __construct(CronTaskTable $cronTaskTable)
    {
        $this->cronTaskTable = $cronTaskTable;
    }

    public function createNewTask(): CronTask
    {
        // TODO:
        $cronTask = (new CronTask())
            ->setName('');

        $this->cronTaskTable->save($cronTask);
    }

    public function getNextTaskByName(string $name): ?CronTask
    {
        // TODO:

        return $this->cronTaskTable->findById(123);
    }
}
