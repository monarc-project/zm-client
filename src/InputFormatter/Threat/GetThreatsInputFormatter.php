<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2023 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\InputFormatter\Threat;

use Monarc\Core\InputFormatter\Threat\GetThreatsInputFormatter as CoreGetThreatsInputFormatter;

class GetThreatsInputFormatter extends CoreGetThreatsInputFormatter
{
    protected static array $allowedSearchFields = [
        'label{languageIndex}',
        'description{languageIndex}',
        'code',
    ];
}
