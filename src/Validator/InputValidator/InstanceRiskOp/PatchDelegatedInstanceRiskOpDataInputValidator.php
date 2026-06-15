<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2026 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Validator\InputValidator\InstanceRiskOp;

class PatchDelegatedInstanceRiskOpDataInputValidator extends UpdateInstanceRiskOpDataInputValidator
{
    private const ALLOWED_FIELDS = [
        'lastReviewDate',
        'reviewFrequency',
        'residualRiskDecision',
        'residualRiskDecidedAt',
        'residualAcceptancePerformedByName',
        'residualAcceptancePerformedByEmail',
        'residualAcceptancePerformedOnBehalf',
        'residualRiskJustification',
    ];

    protected function getRules(): array
    {
        return array_values(array_filter(
            parent::getRules(),
            static fn (array $rule): bool => in_array($rule['name'], self::ALLOWED_FIELDS, true)
        ));
    }
}
