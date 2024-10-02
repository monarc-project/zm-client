<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\InputFormatter\Recommendation;

use Monarc\Core\InputFormatter\AbstractInputFormatter;
use Monarc\FrontOffice\Entity\Recommendation;

class GetRecommendationsInputFormatter extends AbstractInputFormatter
{
    protected const DEFAULT_LIMIT = 25;

    protected static array $allowedSearchFields = [
        'code',
        'description',
    ];

    protected static array $allowedFilterFields = [
        'anr',
        'recommendationSet' => [
            'fieldName' => 'recommendationSet.uuid',
        ],
        'status' => [
            'default' => Recommendation::STATUS_ACTIVE,
            'type' => 'int',
        ],
    ];

    protected static array $ignoredFilterFieldValues = ['status' => 'all'];
}
