<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Export\Service\Traits;

use Monarc\FrontOffice\Entity;

trait InstanceConsequenceExportTrait
{
    use ScaleImpactTypeExportTrait;

    private function prepareInstanceConsequenceData(
        Entity\InstanceConsequence $instanceConsequence,
        int $languageIndex
    ): array {
        /** @var Entity\ScaleImpactType $scaleImpactType */
        $scaleImpactType = $instanceConsequence->getScaleImpactType();

        return [
            'id' => $instanceConsequence->getId(),
            'confidentiality' => $instanceConsequence->getConfidentiality(),
            'integrity' => $instanceConsequence->getIntegrity(),
            'availability' => $instanceConsequence->getAvailability(),
            'isHidden' => $instanceConsequence->isHidden(),
            'scaleImpactType' => $this
                ->prepareScaleImpactTypeAndCommentsData($scaleImpactType, $languageIndex, false, true),
        ];
    }
}
