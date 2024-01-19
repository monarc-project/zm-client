<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\InputFormatter\RecommendationRisk;

use Monarc\Core\InputFormatter\AbstractInputFormatter;

class GetRecommendationRisksInputFormatter extends AbstractInputFormatter
{
    protected static array $allowedFilterFields = [
        'anr',
        'recommendation' => [
            'fieldName' => 'recommendation.uuid',
        ],
        'instanceRisk' => [
            'type' => 'int',
            'fieldName' => 'instanceRisk.id'
        ],
        'instanceRiskOp' => [
            'type' => 'int',
            'fieldName' => 'instanceRiskOp.id'
        ],
    ];
}
