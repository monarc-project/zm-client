<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Export\Service\Traits;

use JetBrains\PhpStorm\ArrayShape;
use Monarc\FrontOffice\Entity;

trait ScaleImpactTypeExportTrait
{
    #[ArrayShape([
        'id' => "int",
        'type' => "int",
        'label' => "string",
        'isSys' => "bool",
        'isHidden' => "bool",
        'scaleComments' => "array",
        'scale' => "array"
    ])] private function prepareScaleImpactTypeAndCommentsData(
        Entity\ScaleImpactType $scaleImpactType,
        int $languageIndex,
        bool $includeComments,
        bool $includeScale
    ): array {
        $scaleComments = [];
        if ($includeComments) {
            foreach ($scaleImpactType->getScaleComments() as $scaleComment) {
                $scaleComments[] = [
                    'scaleIndex' => $scaleComment->getScaleIndex(),
                    'scaleValue' => $scaleComment->getScaleValue(),
                    'comment' => $scaleComment->getComment($languageIndex),
                ];
            }
        }

        $result = [
            'id' => $scaleImpactType->getId(),
            'type' => $scaleImpactType->getType(),
            'label' => $scaleImpactType->getLabel($languageIndex),
            'isSys' => $scaleImpactType->isSys(),
            'isHidden' => $scaleImpactType->isHidden(),
            'scaleComments' => $scaleComments,
        ];
        if ($includeScale) {
            $result['scale'] = [
                'id' => $scaleImpactType->getScale()->getId(),
                'type' => $scaleImpactType->getScale()->getType(),
            ];
        }

        return $result;
    }
}
