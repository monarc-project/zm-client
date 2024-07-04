<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\InputFormatter\Soa;

use Monarc\Core\InputFormatter\AbstractInputFormatter;

class GetSoasInputFormatter extends AbstractInputFormatter
{
    protected static array $allowedSearchFields = [
        'measure.label{languageIndex}',
        'measure.code',
        'remarks',
        'actions',
        'evidences',
    ];

    protected static array $allowedFilterFields = [
        'anr',
        'referential' => [
            'fieldName' => 'measure.referential.uuid',
            'relationConditions' => [
                'measure.anr = :anr',
                'referential.anr = :anr',
            ],
        ],
        'category' => [
            'fieldName' => 'measure.category',
        ],
    ];

    protected static array $ignoredFilterFieldValues = ['category' => 0];

    protected static array $orderParamsToFieldsMap = [
        'm.code' => 'measure.code|LENGTH,measure.code',
    ];
}
