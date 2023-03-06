<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2023 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Service\ConnectedUserService;
use Monarc\Core\Service\InstanceConsequenceService;
use Monarc\FrontOffice\Model\Table as DeprecatedTable;
use Monarc\FrontOffice\Table;

class AnrInstanceConsequenceService extends InstanceConsequenceService
{
    public function __construct(
        Table\InstanceConsequenceTable $instanceConsequenceTable,
        Table\InstanceTable $instanceTable,
        DeprecatedTable\ScaleTable $scaleTable,
        DeprecatedTable\ScaleImpactTypeTable $scaleImpactTypeTable,
        DeprecatedTable\ScaleCommentTable $scaleCommentTable,
        AnrInstanceService $anrInstanceService,
        ConnectedUserService $connectedUserService
    ) {
        parent::__construct(
            $instanceConsequenceTable,
            $instanceTable,
            $scaleTable,
            $scaleImpactTypeTable,
            $scaleCommentTable,
            $anrInstanceService,
            $connectedUserService
        );
    }
}
