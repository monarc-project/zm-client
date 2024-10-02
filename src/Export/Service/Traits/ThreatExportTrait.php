<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Export\Service\Traits;

use JetBrains\PhpStorm\Pure;
use Monarc\FrontOffice\Entity;

trait ThreatExportTrait
{
    #[Pure] private function prepareThreatData(
        Entity\Threat $threat,
        int $languageIndex,
        bool $withEval,
        bool $includeCompleteData = true
    ): array {
        if (!$includeCompleteData) {
            return ['uuid' => $threat->getUuid()];
        }

        return [
            'uuid' => $threat->getUuid(),
            'label' => $threat->getLabel($languageIndex),
            'description' => $threat->getDescription($languageIndex),
            'theme' => $threat->getTheme() !== null ? [
                'id' => $threat->getTheme()->getId(),
                'label' => $threat->getTheme()->getLabel($languageIndex),
            ] : null,
            'status' => $threat->getStatus(),
            'mode' => $threat->getMode(),
            'code' => $threat->getCode(),
            'confidentiality' => $threat->getConfidentiality(),
            'integrity' => $threat->getIntegrity(),
            'availability' => $threat->getAvailability(),
            'trend' => $withEval ? $threat->getTrend() : 0,
            'comment' => $withEval ? $threat->getComment() : '',
            'qualification' => $withEval ? $threat->getQualification() : -1,
        ];
    }
}
