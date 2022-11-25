<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2022 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Validator\InputFormatter\Object;

use Monarc\Core\InputFormatter\Object\GetObjectsInputFormatter as CoreGetObjectsInputFormatter;

class GetObjectsInputFormatter extends CoreGetObjectsInputFormatter
{
    protected static array $allowedFilterFields = [
        'anr',
    ];
}
