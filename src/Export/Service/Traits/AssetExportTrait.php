<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Export\Service\Traits;

use JetBrains\PhpStorm\ArrayShape;
use Monarc\FrontOffice\Entity;

trait AssetExportTrait
{
    #[ArrayShape([
        'uuid' => "string",
        'code' => "string",
        'label' => "string",
        'description' => "string",
        'type' => "int",
        'status' => "int"
    ])] private function prepareAssetData(
        Entity\Asset $asset,
        int $languageIndex,
        bool $includeCompleteData = true
    ): array {
        if (!$includeCompleteData) {
            return ['uuid' => $asset->getUuid()];
        }

        return [
            'uuid' => $asset->getUuid(),
            'code' => $asset->getCode(),
            'label' => $asset->getLabel($languageIndex),
            'description' => $asset->getDescription($languageIndex),
            'type' => $asset->getType(),
            'status' => $asset->getStatus(),
        ];
    }
}
