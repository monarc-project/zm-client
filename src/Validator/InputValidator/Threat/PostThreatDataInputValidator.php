<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Validator\InputValidator\Threat;

use Laminas\Filter\Boolean;
use Laminas\Filter\StringTrim;
use Laminas\Filter\ToInt;
use Monarc\Core\Validator\InputValidator\Threat\PostThreatDataInputValidator as CorePostThreatDataInputValidator;

class PostThreatDataInputValidator extends CorePostThreatDataInputValidator
{
    public function getRules(): array
    {
        return array_merge(parent::getRules(), [
            [
                'name' => 'comment',
                'required' => false,
                'allow_empty' => true,
                'filters' => [
                    ['name' => StringTrim::class],
                ],
                'validators' => [],
            ],
            [
                'name' => 'trend',
                'required' => false,
                'allow_empty' => true,
                'filters' => [
                    ['name' => ToInt::class],
                ],
                'validators' => [],
            ],
            [
                'name' => 'qualification',
                'required' => false,
                'allow_empty' => true,
                'filters' => [
                    ['name' => ToInt::class],
                ],
                'validators' => [],
            ],
            [
                'name' => 'forceQualification',
                'required' => false,
                'allow_empty' => true,
                'filters' => [
                    ['name' => Boolean::class],
                ],
                'validators' => [],
            ],
        ]);
    }
}
