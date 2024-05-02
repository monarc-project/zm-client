<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Export\Service\Traits;

use JetBrains\PhpStorm\ArrayShape;
use Monarc\FrontOffice\Entity;

trait OperationalRiskScaleExportTrait
{
    #[ArrayShape([
        'min' => "int",
        'max' => "int",
        'type' => "int",
        'operationalRiskScaleTypes' => "array",
        'operationalRiskScaleComments' => "array"
    ])] private function prepareOperationalRiskScaleData(Entity\OperationalRiskScale $operationalRiskScale): array
    {
        $scaleTypes = [];
        /** @var Entity\OperationalRiskScaleType $scaleType */
        foreach ($operationalRiskScale->getOperationalRiskScaleTypes() as $scaleType) {
            $scaleTypeComments = [];
            foreach ($scaleType->getOperationalRiskScaleComments() as $scaleTypeComment) {
                $scaleTypeComments[$scaleTypeComment->getId()] = $this->prepareOperationalRiskScaleCommentData(
                    $scaleTypeComment
                );
            }

            $scaleTypes[$scaleType->getId()] = [
                'label' => $scaleType->getLabel(),
                'isHidden' => $scaleType->isHidden(),
                'operationalRiskScaleComments' => $scaleTypeComments,
            ];
        }

        $scaleComments = [];
        foreach ($operationalRiskScale->getOperationalRiskScaleComments() as $scaleComment) {
            if ($scaleComment->getOperationalRiskScaleType() !== null) {
                continue;
            }

            $scaleComments[] = $this->prepareOperationalRiskScaleCommentData($scaleComment);
        }

        return [
            'min' => $operationalRiskScale->getMin(),
            'max' => $operationalRiskScale->getMax(),
            'type' => $operationalRiskScale->getType(),
            'operationalRiskScaleTypes' => $scaleTypes,
            'operationalRiskScaleComments' => $scaleComments,
        ];
    }

    private function prepareOperationalRiskScaleCommentData(Entity\OperationalRiskScaleComment $scaleComment): array
    {
        return [
            'scaleIndex' => $scaleComment->getScaleIndex(),
            'scaleValue' => $scaleComment->getScaleValue(),
            'isHidden' => $scaleComment->isHidden(),
            'comment' => $scaleComment->getComment(),
        ];
    }
}
