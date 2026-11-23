<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2026 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\InputFormatter\Question;

use Monarc\Core\InputFormatter\Question\GetQuestionsInputFormatter as CoreGetQuestionsInputFormatter;

class GetQuestionsInputFormatter extends CoreGetQuestionsInputFormatter
{
    protected static array $allowedFilterFields = [
        'anr',
        'type' => ['type' => 'int', 'inArray' => static::ALLOWED_TYPES],
        'multichoice' => ['type' => 'boolean'],
    ];
}
