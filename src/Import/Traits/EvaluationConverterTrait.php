<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Import\Traits;

trait EvaluationConverterTrait
{
    /**
     * Converts the value within new scales range if it's different between the importing data and the destination anr.
     */
    public function convertValueWithinNewScalesRange(
        int $value,
        int $minOrigin,
        int $maxOrigin,
        int $minDestination,
        int $maxDestination,
        int $defaultValue = -1
    ): int {
        if ($value < 0) {
            return $defaultValue;
        }
        if ($minOrigin === $minDestination && $maxOrigin === $maxDestination) {
            return $value;
        }
        if ($value === $maxOrigin) {
            return $maxDestination;
        }
        if ($value === $minOrigin) {
            return $minDestination;
        }

        $oldRange = $maxOrigin - $minOrigin;
        $newRange = $maxDestination - $minDestination;

        return (int)round(((($value - $minOrigin) * $newRange) / $oldRange) + $minDestination);
    }
}
