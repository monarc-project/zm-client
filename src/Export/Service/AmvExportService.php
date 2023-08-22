<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2023 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Export\Service;

use Monarc\FrontOffice\Model\Entity;

class AmvExportService
{
    // TODO: Inject here, not extend the AmvExportService of core.
    public function __construct()
    {
    }

    public function generateExportArray(Entity\Amv $amv, bool $withEval = false): array
    {
        // TODO: call the injected core export class.
        return [];
    }
}
