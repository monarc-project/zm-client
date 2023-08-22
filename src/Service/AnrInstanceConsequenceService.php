<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2023 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Model\Entity\AnrSuperClass;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\Core\Service\InstanceConsequenceService;
use Monarc\FrontOffice\Model\Entity\Anr;
use Monarc\FrontOffice\Table;

class AnrInstanceConsequenceService extends InstanceConsequenceService
{
    // TODO: check if all the tables inherit the core ones.
    public function __construct(
        Table\InstanceConsequenceTable $instanceConsequenceTable,
        Table\InstanceTable $instanceTable,
        Table\ScaleTable $scaleTable,
        Table\ScaleImpactTypeTable $scaleImpactTypeTable,
        Table\ScaleCommentTable $scaleCommentTable,
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

    /**
     * @param Anr $anr
     */
    protected function getLanguageIndex(AnrSuperClass $anr): int
    {
        return $anr->getLanguage();
    }
}
