<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2023 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Export\Service;

use Monarc\FrontOffice\Entity;
use Monarc\FrontOffice\Table\OperationalRiskScaleTable;

class OperationalRiskScalesExportService
{
    private OperationalRiskScaleTable $operationalRiskScaleTable;

    public function __construct(OperationalRiskScaleTable $operationalRiskScaleTable)
    {
        $this->operationalRiskScaleTable = $operationalRiskScaleTable;
    }

    // TODO: ...
    public function generateExportArray(Entity\Anr $anr): array
    {
        $result = [];
        /** @var Entity\OperationalRiskScale[] $operationalRiskScales */
        $operationalRiskScales = $this->operationalRiskScaleTable->findByAnr($anr);
        foreach ($operationalRiskScales as $scale) {
            $scaleTypes = [];
            foreach ($scale->getOperationalRiskScaleTypes() as $scaleType) {
                $scaleTypeComments = [];
                foreach ($scaleType->getOperationalRiskScaleComments() as $scaleTypeComment) {
                    $scaleTypeComments[] = $this->getOperationalRiskScaleCommentData(
                        $scaleTypeComment,
                        $operationalRisksAndScalesTranslations
                    );
                }

                $typeTranslation = $operationalRisksAndScalesTranslations[$scaleType->getLabelTranslationKey()];
                $scaleTypes[] = [
                    'id' => $scaleType->getId(),
                    'isHidden' => $scaleType->isHidden(),
                    'labelTranslationKey' => $scaleType->getLabelTranslationKey(),
                    'translation' => [
                        'key' => $typeTranslation->getKey(),
                        'lang' => $typeTranslation->getLang(),
                        'value' => $typeTranslation->getValue(),
                    ],
                    'operationalRiskScaleComments' => $scaleTypeComments,
                ];
            }

            $scaleComments = [];
            foreach ($scale->getOperationalRiskScaleComments() as $scaleComment) {
                if ($scaleComment->getOperationalRiskScaleType() !== null) {
                    continue;
                }

                $scaleComments[] = $this->getOperationalRiskScaleCommentData(
                    $scaleComment,
                    $operationalRisksAndScalesTranslations
                );
            }

            $result[$scale->getType()] = [
                'id' => $scale->getId(),
                'min' => $scale->getMin(),
                'max' => $scale->getMax(),
                'type' => $scale->getType(),
                'operationalRiskScaleTypes' => $scaleTypes,
                'operationalRiskScaleComments' => $scaleComments,
            ];
        }

        return $result;
    }

    protected function getOperationalRiskScaleCommentData(
        Entity\OperationalRiskScaleComment $scaleComment,
        array $operationalRisksAndScalesTranslations
    ): array {
        $commentTranslation = $operationalRisksAndScalesTranslations[$scaleComment->getCommentTranslationKey()];

        return [
            'id' => $scaleComment->getId(),
            'scaleIndex' => $scaleComment->getScaleIndex(),
            'scaleValue' => $scaleComment->getScaleValue(),
            'isHidden' => $scaleComment->isHidden(),
            'commentTranslationKey' => $scaleComment->getCommentTranslationKey(),
            'translation' => [
                'key' => $commentTranslation->getKey(),
                'lang' => $commentTranslation->getLang(),
                'value' => $commentTranslation->getValue(),
            ],
        ];
    }
}
