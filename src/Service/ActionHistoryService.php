<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2025 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Service\ActionHistoryService as CoreActionHistoryService;
use Monarc\FrontOffice\Table\ActionHistoryTable;

class ActionHistoryService extends CoreActionHistoryService
{
    public function __construct(ActionHistoryTable $actionHistoryTable)
    {
        parent::__construct($actionHistoryTable);
    }
}
