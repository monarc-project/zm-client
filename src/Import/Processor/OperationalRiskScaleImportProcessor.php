<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Import\Processor;

use Monarc\Core\Entity\OperationalRiskScaleSuperClass;
use Monarc\Core\Entity\OperationalRiskScaleTypeSuperClass;
use Monarc\Core\Entity\ScaleSuperClass;
use Monarc\Core\Entity\UserSuperClass;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Entity;
use Monarc\FrontOffice\Import\Helper\ImportCacheHelper;
use Monarc\FrontOffice\Import\Traits\EvaluationConverterTrait;
use Monarc\FrontOffice\Service\AnrInstanceRiskOpService;
use Monarc\FrontOffice\Table;

class OperationalRiskScaleImportProcessor
{
    use EvaluationConverterTrait;

    private UserSuperClass $connectedUser;

    public function __construct(
        private Table\OperationalRiskScaleTable $operationalRiskScaleTable,
        private Table\OperationalRiskScaleTypeTable $operationalRiskScaleTypeTable,
        private Table\OperationalRiskScaleCommentTable $operationalRiskScaleCommentTable,
        private Table\InstanceRiskOpTable $instanceRiskOpTable,
        private Table\OperationalInstanceRiskScaleTable $operationalInstanceRiskScaleTable,
        private ImportCacheHelper $importCacheHelper,
        private AnrInstanceRiskOpService $anrInstanceRiskOpService,
        ConnectedUserService $connectedUserService
    ) {
        $this->connectedUser = $connectedUserService->getConnectedUser();
    }

    public function adjustOperationalRisksScaleValuesBasedOnNewScales(Entity\Anr $anr, array $data): void
    {
        /** @var Entity\InstanceRiskOp[] $operationalInstanceRisks */
        $operationalInstanceRisks = $this->instanceRiskOpTable->findByAnr($anr);
        if (!empty($operationalInstanceRisks)) {
            $currentOperationalRiskScalesData = $this->getCurrentOperationalRiskScalesData($anr);
            $externalOperationalRiskScalesData = $this->getExternalOperationalRiskScalesData($anr, $data);

            foreach ($operationalInstanceRisks as $operationalInstanceRisk) {
                $this->adjustOperationalRisksProbabilityScales(
                    $operationalInstanceRisk,
                    $currentOperationalRiskScalesData[OperationalRiskScaleSuperClass::TYPE_LIKELIHOOD],
                    $externalOperationalRiskScalesData[OperationalRiskScaleSuperClass::TYPE_LIKELIHOOD]
                );

                foreach ($operationalInstanceRisk->getOperationalInstanceRiskScales() as $instanceRiskScale) {
                    $this->adjustOperationalInstanceRisksScales(
                        $instanceRiskScale,
                        $currentOperationalRiskScalesData[OperationalRiskScaleSuperClass::TYPE_IMPACT],
                        $externalOperationalRiskScalesData[OperationalRiskScaleSuperClass::TYPE_IMPACT]
                    );
                }

                $this->instanceRiskOpTable->save($operationalInstanceRisk, false);

                $this->anrInstanceRiskOpService->updateRiskCacheValues($operationalInstanceRisk);
            }
        }
    }

    public function adjustOperationalRisksProbabilityScales(
        Entity\InstanceRiskOp $operationalInstanceRisk,
        array $fromOperationalRiskScalesData,
        array $toOperationalRiskScalesData
    ): void {
        foreach (['NetProb', 'BrutProb', 'TargetedProb'] as $likelihoodScaleName) {
            $operationalInstanceRisk->{'set' . $likelihoodScaleName}(
                $this->convertValueWithinNewScalesRange(
                    $operationalInstanceRisk->{'get' . $likelihoodScaleName}(),
                    $fromOperationalRiskScalesData['min'],
                    $fromOperationalRiskScalesData['max'],
                    $toOperationalRiskScalesData['min'],
                    $toOperationalRiskScalesData['max']
                )
            );
        }
    }

    public function adjustOperationalInstanceRisksScales(
        Entity\OperationalInstanceRiskScale $instanceRiskScale,
        array $fromOperationalRiskScalesData,
        array $toOperationalRiskScalesData
    ): void {
        foreach (['NetValue', 'BrutValue', 'TargetedValue'] as $impactScaleName) {
            $scaleImpactValue = $instanceRiskScale->{'get' . $impactScaleName}();
            if ($scaleImpactValue === -1) {
                continue;
            }
            $scaleImpactIndex = array_search(
                $scaleImpactValue,
                $fromOperationalRiskScalesData['commentsIndexToValueMap'],
                true
            );
            if ($scaleImpactIndex === false) {
                continue;
            }

            $approximatedIndex = $this->convertValueWithinNewScalesRange(
                $scaleImpactIndex,
                $fromOperationalRiskScalesData['min'],
                $fromOperationalRiskScalesData['max'],
                $toOperationalRiskScalesData['min'],
                $toOperationalRiskScalesData['max']
            );

            $approximatedValueToNewScales = $toOperationalRiskScalesData['commentsIndexToValueMap'][$approximatedIndex]
                ?? $scaleImpactValue;
            $instanceRiskScale->{'set' . $impactScaleName}($approximatedValueToNewScales);

            $this->operationalInstanceRiskScaleTable->save($instanceRiskScale, false);
        }
    }

    public function getCurrentOperationalRiskScalesData(Entity\Anr $anr): array
    {
        if (empty($this->importCacheHelper->isCacheKeySet('current_operational_risk_scales_data'))) {
            /** @var Entity\OperationalRiskScale $operationalRiskScale */
            foreach ($this->operationalRiskScaleTable->findByAnr($anr) as $operationalRiskScale) {
                [$scaleTypesData, $commentsIndexToValueMap] = $this->prepareScaleTypesDataAndCommentsIndexToValueMap(
                    $operationalRiskScale
                );

                $this->importCacheHelper->addItemToArrayCache('current_operational_risk_scales_data', [
                    'min' => $operationalRiskScale->getMin(),
                    'max' => $operationalRiskScale->getMax(),
                    'object' => $operationalRiskScale,
                    'commentsIndexToValueMap' => $commentsIndexToValueMap,
                    'operationalRiskScaleTypes' => $scaleTypesData,
                ], $operationalRiskScale->getType());
            }
        }

        return $this->importCacheHelper->getItemFromArrayCache('current_operational_risk_scales_data');
    }

    /**
     * Prepares and caches the new/importing operational risks scales.
     * The format can be different, depends on the version (before v2.11.0 and after).
     */
    public function getExternalOperationalRiskScalesData(Entity\Anr $anr, array $data): array
    {
        if (!empty($data)) {
            $this->prepareExternalOperationalRiskScalesDataCache($anr, $data);
        }

        return $this->importCacheHelper->getItemFromArrayCache('external_operational_risk_scales_data');
    }

    public function prepareExternalOperationalRiskScalesDataCache(Entity\Anr $anr, array $data): void
    {
        if (!$this->importCacheHelper->isCacheKeySet('external_operational_risk_scales_data')) {
            /* Populate with informational risks scales if there is an import of a file exported prior v2.11.0. */
            $scalesDataResult = [
                OperationalRiskScaleSuperClass::TYPE_IMPACT => [
                    'min' => 0,
                    'max' => $data['scales'][ScaleSuperClass::TYPE_IMPACT]['max']
                        - $data['scales'][ScaleSuperClass::TYPE_IMPACT]['min'],
                    'commentsIndexToValueMap' => [],
                    'operationalRiskScaleTypes' => [],
                    'operationalRiskScaleComments' => [],
                ],
                OperationalRiskScaleSuperClass::TYPE_LIKELIHOOD => [
                    'min' => $data['scales'][ScaleSuperClass::TYPE_THREAT]['min'],
                    'max' => $data['scales'][ScaleSuperClass::TYPE_THREAT]['max'],
                    'commentsIndexToValueMap' => [],
                    'operationalRiskScaleTypes' => [],
                    'operationalRiskScaleComments' => [],
                ],
            ];
            if (!empty($data['operationalRiskScales'])) {
                /* Overwrite the values for the version >= v2.11.0. */
                foreach ($data['operationalRiskScales'] as $operationalRiskScaleData) {
                    $scaleType = $operationalRiskScaleData['type'];
                    $scalesDataResult[$scaleType]['min'] = $operationalRiskScaleData['min'];
                    $scalesDataResult[$scaleType]['max'] = $operationalRiskScaleData['max'];

                    /* Build the map of the comments index <=> values relation. */
                    foreach ($operationalRiskScaleData['operationalRiskScaleTypes'] as $scaleTypeData) {
                        $scalesDataResult[$scaleType]['operationalRiskScaleTypes'][] = $scaleTypeData;
                        /* All the scale comment have the same index->value corresponding values, so populating once. */
                        if (empty($scalesDataResult[$scaleType]['commentsIndexToValueMap'])) {
                            foreach ($scaleTypeData['operationalRiskScaleComments'] as $scaleTypeComment) {
                                if (!$scaleTypeComment['isHidden']) {
                                    $scalesDataResult[$scaleType]['commentsIndexToValueMap']
                                    [$scaleTypeComment['scaleIndex']] = $scaleTypeComment['scaleValue'];
                                }
                            }
                        }
                    }

                    $scalesDataResult[$scaleType]['operationalRiskScaleComments']
                        = $operationalRiskScaleData['operationalRiskScaleComments'];
                }
            } else {
                /* Convert comments and types from informational risks to operational (new format). */
                $scaleMin = $data['scales'][ScaleSuperClass::TYPE_IMPACT]['min'];
                foreach (OperationalRiskScaleTypeSuperClass::getDefaultScalesImpacts() as $index => $scaleTypeLabels) {
                    /* Previous ROLFP scales impact types (types indexes were [4, 5, 6, 7, 8]). */
                    $scalesDataResult[ScaleSuperClass::TYPE_IMPACT]['operationalRiskScaleTypes'][$index + 4] = [
                        'isHidden' => false,
                        'label' => $scaleTypeLabels[$anr->getLanguageCode()],
                    ];
                }
                foreach ($data['scalesComments'] as $scaleComment) {
                    $scaleType = $scaleComment['scale']['type'];
                    if (!\in_array($scaleType, [ScaleSuperClass::TYPE_IMPACT, ScaleSuperClass::TYPE_THREAT], true)) {
                        continue;
                    }

                    $scaleCommentLabel = $scaleComment['comment'] ?? $scaleComment['comment' . $anr->getLanguage()];
                    if ($scaleType === ScaleSuperClass::TYPE_THREAT) {
                        $scalesDataResult[$scaleType]['operationalRiskScaleComments'][] = [
                            'scaleIndex' => $scaleComment['val'],
                            'scaleValue' => $scaleComment['val'],
                            'isHidden' => false,
                            'comment' => $scaleCommentLabel,
                        ];
                    } elseif ($scaleType === ScaleSuperClass::TYPE_IMPACT && $scaleComment['val'] >= $scaleMin) {
                        $scaleIndex = $scaleComment['val'] - $scaleMin;
                        $scaleTypePosition = $scaleComment['scaleImpactType']['position'];
                        if (isset($scalesDataResult[$scaleType]['operationalRiskScaleTypes'][$scaleTypePosition])) {
                            $scalesDataResult[$scaleType]['operationalRiskScaleTypes'][$scaleTypePosition][
                                'operationalRiskScaleComments'
                            ][] = [
                                'scaleIndex' => $scaleIndex,
                                'scaleValue' => $scaleComment['val'],
                                'isHidden' => false,
                                'comment' => $scaleCommentLabel,
                            ];

                            $scalesDataResult[$scaleType]['commentsIndexToValueMap'][$scaleIndex]
                                = $scaleComment['val'];
                        }
                    }
                }
            }

            $this->importCacheHelper->addItemToArrayCache(
                'external_operational_risk_scales_data',
                $scalesDataResult[OperationalRiskScaleSuperClass::TYPE_IMPACT],
                OperationalRiskScaleSuperClass::TYPE_IMPACT
            );
            $this->importCacheHelper->addItemToArrayCache(
                'external_operational_risk_scales_data',
                $scalesDataResult[OperationalRiskScaleSuperClass::TYPE_LIKELIHOOD],
                OperationalRiskScaleSuperClass::TYPE_LIKELIHOOD
            );
        }
    }

    public function updateOperationalRisksScalesAndRelatedInstances(Entity\Anr $anr, array $data): void
    {
        $externalOperationalScalesData = $this->getExternalOperationalRiskScalesData($anr, $data);
        /** @var Entity\OperationalRiskScale $operationalRiskScale */
        foreach ($this->operationalRiskScaleTable->findByAnr($anr) as $operationalRiskScale) {
            $externalScaleData = $externalOperationalScalesData[$operationalRiskScale->getType()];
            $currentScaleLevelDifferenceFromExternal = $operationalRiskScale->getMax() - $externalScaleData['max'];
            $operationalRiskScale
                ->setAnr($anr)
                ->setMin($externalScaleData['min'])
                ->setMax($externalScaleData['max'])
                ->setUpdater($this->connectedUser->getEmail());

            /* This is currently only applicable for impact scales type. */
            $createdScaleTypes = [];
            $matchedScaleTypes = [];
            foreach ($externalScaleData['operationalRiskScaleTypes'] as $scaleTypeData) {
                $isScaleTypeMatched = true;
                $externalScaleTypeLabel = $scaleTypeData['label'] ?? $scaleTypeData['translation']['value'];
                $operationalRiskScaleType = $this->matchScaleTypeWithScaleTypesListByLabel(
                    $operationalRiskScale->getOperationalRiskScaleTypes(),
                    $externalScaleTypeLabel
                );
                if ($operationalRiskScaleType === null) {
                    $isScaleTypeMatched = false;
                    $operationalRiskScaleType = (new Entity\OperationalRiskScaleType())
                        ->setAnr($anr)
                        ->setOperationalRiskScale($operationalRiskScale)
                        ->setLabel($externalScaleTypeLabel)
                        ->setCreator($this->connectedUser->getEmail());

                    $createdScaleTypes[$operationalRiskScaleType->getLabel()] = $operationalRiskScaleType;
                } elseif ($currentScaleLevelDifferenceFromExternal !== 0) {
                    $matchedScaleTypes[$operationalRiskScaleType->getId()] = $operationalRiskScaleType;
                }

                /* The map is used to match for the importing operational risks, scale values with scale types. */
                $this->importCacheHelper->addItemToArrayCache(
                    'operational_risk_scale_type_label_to_old_id',
                    $scaleTypeData['id'],
                    $operationalRiskScaleType->getLabel()
                );

                $operationalRiskScaleType->setIsHidden($scaleTypeData['isHidden']);
                $this->operationalRiskScaleTypeTable->save($operationalRiskScaleType, false);

                foreach ($scaleTypeData['operationalRiskScaleComments'] as $scaleTypeCommentData) {
                    $this->createOrUpdateOperationalRiskScaleComment(
                        $anr,
                        $isScaleTypeMatched,
                        $operationalRiskScale,
                        $scaleTypeCommentData,
                        $operationalRiskScaleType->getOperationalRiskScaleComments(),
                        $operationalRiskScaleType
                    );
                }
            }

            /* Create relations of all the created scales with existed risks. */
            if (!empty($createdScaleTypes)) {
                /** @var Entity\InstanceRiskOp $operationalInstanceRisk */
                foreach ($this->instanceRiskOpTable->findByAnr($anr) as $operationalInstanceRisk) {
                    foreach ($createdScaleTypes as $createdScaleType) {
                        $operationalInstanceRiskScale = (new Entity\OperationalInstanceRiskScale())
                            ->setAnr($anr)
                            ->setOperationalRiskScaleType($createdScaleType)
                            ->setOperationalInstanceRisk($operationalInstanceRisk)
                            ->setCreator($this->connectedUser->getEmail());
                        $this->operationalInstanceRiskScaleTable->save($operationalInstanceRiskScale, false);
                    }
                }
            }

            $maxIndexForLikelihood = 0;
            /* This is currently applicable only for likelihood scales type */
            foreach ($externalScaleData['object']->getOperationalRiskScaleComments() as $scaleCommentData) {
                $this->createOrUpdateOperationalRiskScaleComment(
                    $anr,
                    true,
                    $operationalRiskScale,
                    $scaleCommentData,
                    $operationalRiskScale->getOperationalRiskScaleComments(),
                );
                $maxIndexForLikelihood = (int)$scaleCommentData['scaleIndex'] > $maxIndexForLikelihood
                    ? (int)$scaleCommentData['scaleIndex']
                    : $maxIndexForLikelihood;
            }
            /* Manage a case when the scale (probability) is not matched and level higher than external. */
            if ($maxIndexForLikelihood !== 0
                && $operationalRiskScale->getType() === OperationalRiskScaleSuperClass::TYPE_LIKELIHOOD
            ) {
                foreach ($operationalRiskScale->getOperationalRiskScaleComments() as $comment) {
                    if ($comment->getScaleIndex() >= $maxIndexForLikelihood) {
                        $comment->setIsHidden(true);
                        $this->operationalRiskScaleCommentTable->save($comment, false);
                    }
                }
            }

            /* Validate if any existed comments are now out of the new scales bound and if their values are valid.
                Also, if their comments are complete per scale's level. */
            if ($currentScaleLevelDifferenceFromExternal !== 0) {
                /** @var Entity\OperationalRiskScaleType $operationalRiskScaleType */
                foreach ($operationalRiskScale->getOperationalRiskScaleTypes() as $operationalRiskScaleType) {
                    /* Ignore the currently created scale types. */
                    if (\array_key_exists($operationalRiskScaleType->getLabel(), $createdScaleTypes)) {
                        continue;
                    }

                    if ($currentScaleLevelDifferenceFromExternal < 0
                        && !\array_key_exists($operationalRiskScaleType->getId(), $matchedScaleTypes)
                    ) {
                        /* The scales type was not matched and the current scales level is lower than external,
                            so we need to create missing empty scales comments. */
                        $commentIndex = $operationalRiskScale->getMax() + $currentScaleLevelDifferenceFromExternal + 1;
                        $commentIndexToValueMap = $externalOperationalScalesData[
                            OperationalRiskScaleSuperClass::TYPE_IMPACT
                        ]['commentsIndexToValueMap'];
                        while ($commentIndex <= $operationalRiskScale->getMax()) {
                            $this->createOrUpdateOperationalRiskScaleComment($anr, false, $operationalRiskScale, [
                                'scaleIndex' => $commentIndex,
                                'scaleValue' => $commentIndexToValueMap[$commentIndex],
                                'isHidden' => false,
                                'comment' => '',
                            ], [], $operationalRiskScaleType);
                            $commentIndex++;
                        }

                        continue;
                    }

                    if ($currentScaleLevelDifferenceFromExternal > 0) {
                        $commentIndexToValueMap = $externalOperationalScalesData[
                            OperationalRiskScaleSuperClass::TYPE_IMPACT
                        ]['commentsIndexToValueMap'];
                        $maxValue = $commentIndexToValueMap[$operationalRiskScale->getMax()];
                        if (\array_key_exists($operationalRiskScaleType->getId(), $matchedScaleTypes)) {
                            /* The scales type was matched and the current scales level is higher than external,
                                so we need to hide their comments and validate values. */
                            foreach ($matchedScaleTypes as $matchedScaleType) {
                                $this->processCurrentScalesCommentsWhenLimitIsHigher(
                                    $operationalRiskScale,
                                    $matchedScaleType,
                                    $maxValue
                                );
                            }
                        } else {
                            /* Manage a case when the scale is not matched and level higher than external */
                            $this->processCurrentScalesCommentsWhenLimitIsHigher(
                                $operationalRiskScale,
                                $operationalRiskScaleType,
                                $maxValue
                            );
                        }
                    }
                }
            }

            $this->operationalRiskScaleTable->save($operationalRiskScale, false);

            [$scaleTypesData, $commentsIndexToValueMap] = $this->prepareScaleTypesDataAndCommentsIndexToValueMap(
                $operationalRiskScale
            );
            /* Update the values in the cache. */
            $this->importCacheHelper->addItemToArrayCache('current_operational_risk_scales_data', [
                'min' => $operationalRiskScale->getMin(),
                'max' => $operationalRiskScale->getMax(),
                'object' => $operationalRiskScale,
                'commentsIndexToValueMap' => $commentsIndexToValueMap,
                'operationalRiskScaleTypes' => $scaleTypesData,
            ], $operationalRiskScale->getType());
        }
    }

    public function createOrUpdateOperationalRiskScaleComment(
        Entity\Anr $anr,
        bool $isMatchRequired,
        Entity\OperationalRiskScale $operationalRiskScale,
        array $scaleCommentData,
        iterable $scaleCommentsToMatchWith,
        ?Entity\OperationalRiskScaleType $operationalRiskScaleType = null
    ): Entity\OperationalRiskScaleComment {
        $operationalRiskScaleComment = null;
        if ($isMatchRequired) {
            $operationalRiskScaleComment = $this->matchScaleCommentDataWithScaleCommentsList(
                $operationalRiskScale,
                $scaleCommentData,
                $scaleCommentsToMatchWith,
            );
        }
        if ($operationalRiskScaleComment === null) {
            $operationalRiskScaleComment = (new Entity\OperationalRiskScaleComment())
                ->setAnr($anr)
                ->setOperationalRiskScale($operationalRiskScale)
                ->setComment($scaleCommentData['comment'] ?? $scaleCommentData['translation']['value'])
                ->setCreator($this->connectedUser->getEmail());
        }

        if ($operationalRiskScaleType !== null) {
            $operationalRiskScaleComment->setOperationalRiskScaleType($operationalRiskScaleType);
        }

        $operationalRiskScaleComment
            ->setScaleIndex($scaleCommentData['scaleIndex'])
            ->setScaleValue($scaleCommentData['scaleValue'])
            ->setIsHidden($scaleCommentData['isHidden']);
        $this->operationalRiskScaleCommentTable->save($operationalRiskScaleComment, false);

        return $operationalRiskScaleComment;
    }

    public function prepareScaleTypesDataAndCommentsIndexToValueMap(
        Entity\OperationalRiskScale $operationalRiskScale
    ): array {
        $scaleTypesData = [];
        $commentsIndexToValueMap = [];
        /* Build the map of the comments index <=> values relation. */
        foreach ($operationalRiskScale->getOperationalRiskScaleTypes() as $scaleType) {
            /* The operational risk scale types object is used to recreate operational instance risk scales. */
            $scaleTypesData[]['object'] = $scaleType;
            /* All the scale comment have the same index -> value corresponding values, so populating once. */
            if (empty($commentsIndexToValueMap)) {
                foreach ($scaleType->getOperationalRiskScaleComments() as $scaleTypeComment) {
                    if (!$scaleTypeComment->isHidden()) {
                        $commentsIndexToValueMap[$scaleTypeComment->getScaleIndex()] =
                            $scaleTypeComment->getScaleValue();
                    }
                }
            }
        }

        return [$scaleTypesData, $commentsIndexToValueMap];
    }

    /**
     * @param Entity\OperationalRiskScaleComment[] $operationalRiskScaleComments
     */
    private function matchScaleCommentDataWithScaleCommentsList(
        Entity\OperationalRiskScale $operationalRiskScale,
        array $scaleTypeCommentData,
        iterable $operationalRiskScaleComments,
    ): ?Entity\OperationalRiskScaleComment {
        foreach ($operationalRiskScaleComments as $operationalRiskScaleComment) {
            if ($operationalRiskScaleComment->getScaleIndex() === $scaleTypeCommentData['scaleIndex']
                && $operationalRiskScale->getId() === $operationalRiskScaleComment->getOperationalRiskScale()->getId()
            ) {
                $commentLabel = $scaleTypeCommentData['comment'] ?? $scaleTypeCommentData['translation']['value'];
                if ($operationalRiskScaleComment->getComment() !== $commentLabel) {
                    $this->operationalRiskScaleCommentTable->save($operationalRiskScaleComment, false);
                }

                return $operationalRiskScaleComment;
            }
        }

        return null;
    }

    /**
     * Matches local operational risks' scale types with importing ones by label or translation value (prior v2.13.1).
     *
     * @param Entity\OperationalRiskScaleType[] $operationalRiskScaleTypes
     */
    private function matchScaleTypeWithScaleTypesListByLabel(
        iterable $operationalRiskScaleTypes,
        string $scaleTypeLabel,
    ): ?Entity\OperationalRiskScaleType {
        foreach ($operationalRiskScaleTypes as $operationalRiskScaleType) {
            if ($operationalRiskScaleType->getLabel() === $scaleTypeLabel) {
                return $operationalRiskScaleType;
            }
        }

        return null;
    }

    private function processCurrentScalesCommentsWhenLimitIsHigher(
        Entity\OperationalRiskScale $operationalRiskScale,
        Entity\OperationalRiskScaleType $operationalRiskScaleType,
        int $maxValue
    ): void {
        foreach ($operationalRiskScaleType->getOperationalRiskScaleComments() as $comment) {
            $isHidden = $operationalRiskScale->getMin() > $comment->getScaleIndex()
                || $operationalRiskScale->getMax() < $comment->getScaleIndex();
            $comment->setIsHidden($isHidden);
            if ($isHidden && $maxValue >= $comment->getScaleValue()) {
                $comment->setScaleValue(++$maxValue);
            }

            $this->operationalRiskScaleCommentTable->save($comment, false);
        }
    }
}
