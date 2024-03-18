<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2023 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Export\Service;

use Monarc\Core\Entity\AnrSuperClass;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\Core\Table\AssetTable;
use Monarc\FrontOffice\Entity\Anr;

class AssetExportService
{
    public function __construct(
        AssetTable $assetTable,
        AmvExportService $amvExportService,
        ConnectedUserService $connectedUserService
    ) {
    }

    // TODO: add the functionality similar to the Core one

    protected function getLanguageIndex(?AnrSuperClass $anr): int
    {
        if ($anr !== null) {
            /** @var Anr $anr */
            return $anr->getLanguage();
        }

        return parent::getLanguageIndex($anr);
    }
}
