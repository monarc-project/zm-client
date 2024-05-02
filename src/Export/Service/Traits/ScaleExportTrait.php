<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Export\Service\Traits;

use JetBrains\PhpStorm\ArrayShape;
use Monarc\FrontOffice\Entity;

trait ScaleExportTrait
{
    use ScaleImpactTypeExportTrait;

    #[ArrayShape([
        'min' => "int",
        'max' => "int",
        'type' => "int",
        'scaleImpactTypes' => "array",
    ])] private function prepareScaleData(Entity\Scale $scale, int $languageIndex): array
    {
        return [
            'min' => $scale->getMin(),
            'max' => $scale->getMax(),
            'type' => $scale->getType(),
            'scaleImpactTypes' => $this->prepareScaleImpactTypesData($scale, $languageIndex),
        ];
    }

    private function prepareScaleImpactTypesData(Entity\Scale $scale, int $languageIndex): array
    {
        $result = [];
        foreach ($scale->getScaleImpactTypes() as $scaleImpactType) {
            $result[$scaleImpactType->getId()] = $this
                ->prepareScaleImpactTypeAndCommentsData($scaleImpactType, $languageIndex, true, false);
        }

        return $result;
    }
}
