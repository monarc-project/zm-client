<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2023 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\InputFormatter\Amv;

use Monarc\Core\InputFormatter\Amv\GetAmvsInputFormatter as CoreGetAmvsInputFormatter;

class GetAmvsInputFormatter extends CoreGetAmvsInputFormatter
{
    protected static array $allowedSearchFields = [
        'asset.code',
        'asset.label{languageIndex}',
        'asset.description{languageIndex}',
        'threat.code',
        'threat.label{languageIndex}',
        'threat.description{languageIndex}',
        'vulnerability.code',
        'vulnerability.label{languageIndex}',
        'vulnerability.description{languageIndex}',
    ];
}
