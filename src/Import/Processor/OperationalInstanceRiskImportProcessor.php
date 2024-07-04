<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Import\Processor;

use Monarc\Core\Entity\InstanceRiskOpSuperClass;
use Monarc\Core\Entity\OperationalRiskScaleSuperClass;
use Monarc\Core\Entity\UserSuperClass;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Entity;
use Monarc\FrontOffice\Import\Helper\ImportCacheHelper;
use Monarc\FrontOffice\Service\AnrInstanceRiskOpService;
use Monarc\FrontOffice\Service\AnrRecommendationRiskService;
use Monarc\FrontOffice\Service\InstanceRiskOwnerService;
use Monarc\FrontOffice\Table;

class OperationalInstanceRiskImportProcessor
{
    private UserSuperClass $connectedUser;

    public function __construct(
        private Table\OperationalInstanceRiskScaleTable $operationalInstanceRiskScaleTable,
        private Table\OperationalRiskScaleTypeTable $operationalRiskScaleTypeTable,
        private Table\InstanceRiskOpTable $instanceRiskOpTable,
        private ImportCacheHelper $importCacheHelper,
        private OperationalRiskScaleImportProcessor $operationalRiskScaleImportProcessor,
        private RecommendationImportProcessor $recommendationImportProcessor,
        private InstanceRiskOwnerService $instanceRiskOwnerService,
        private AnrInstanceRiskOpService $anrInstanceRiskOpService,
        private AnrRecommendationRiskService $anrRecommendationRiskService,
        ConnectedUserService $connectedUserService
    ) {
        $this->connectedUser = $connectedUserService->getConnectedUser();
    }

    public function processOperationalInstanceRisks(
        array $data,
        Entity\Anr $anr,
        Entity\Instance $instance,
        Entity\MonarcObject $monarcObject,
        bool $includeEval,
        bool $isImportTypeAnr
    ): void {
        if (empty($data['risksop'])) {
            return;
        }

        $operationalRiskScalesData = $this->operationalRiskScaleImportProcessor
            ->getCurrentOperationalRiskScalesData($anr);
        $externalOperationalRiskScalesData = [];
        $areScalesLevelsOfLikelihoodDifferent = false;
        $areImpactScaleTypesValuesDifferent = false;
        $matchedScaleTypesMap = [];
        if ($includeEval && !$isImportTypeAnr) {
            $externalOperationalRiskScalesData = $this->operationalRiskScaleImportProcessor
                ->getExternalOperationalRiskScalesData($anr, $data);
            $areScalesLevelsOfLikelihoodDifferent = $this->areLikelihoodScalesLevelsOfTypeDifferent(
                $operationalRiskScalesData,
                $externalOperationalRiskScalesData
            );
            $areImpactScaleTypesValuesDifferent = $this->areImpactScaleTypeValuesDifferent(
                $operationalRiskScalesData,
                $externalOperationalRiskScalesData
            );
            $matchedScaleTypesMap = $this->matchAndGetOperationalRiskScaleTypesMap(
                $operationalRiskScalesData,
                $externalOperationalRiskScalesData
            );
        }
        $oldInstanceRiskFieldsMapToScaleTypesFields = [
            ['brutR' => 'BrutValue', 'netR' => 'NetValue', 'targetedR' => 'TargetedValue'],
            ['brutO' => 'BrutValue', 'netO' => 'NetValue', 'targetedO' => 'TargetedValue'],
            ['brutL' => 'BrutValue', 'netL' => 'NetValue', 'targetedL' => 'TargetedValue'],
            ['brutF' => 'BrutValue', 'netF' => 'NetValue', 'targetedF' => 'TargetedValue'],
            ['brutP' => 'BrutValue', 'netP' => 'NetValue', 'targetedP' => 'TargetedValue'],
        ];

        // TODO: if it will be called from the new structure logic adopt to check if "withRecommendations".
        if (!empty($data['recs'])) {
            $this->recommendationImportProcessor->prepareRecommendationsCache($anr);
        }

        foreach ($data['risksop'] as $operationalRiskData) {
            $operationalInstanceRisk = (new Entity\InstanceRiskOp())
                ->setAnr($anr)
                ->setInstance($instance)
                ->setObject($monarcObject)
                ->setRiskCacheLabels([
                    'riskCacheLabel1' => $operationalRiskData['riskCacheLabel1'],
                    'riskCacheLabel2' => $operationalRiskData['riskCacheLabel2'],
                    'riskCacheLabel3' => $operationalRiskData['riskCacheLabel3'],
                    'riskCacheLabel4' => $operationalRiskData['riskCacheLabel4'],
                ])
                ->setRiskCacheDescriptions([
                    'riskCacheDescription1' => $operationalRiskData['riskCacheDescription1'],
                    'riskCacheDescription2' => $operationalRiskData['riskCacheDescription2'],
                    'riskCacheDescription3' => $operationalRiskData['riskCacheDescription3'],
                    'riskCacheDescription4' => $operationalRiskData['riskCacheDescription4'],
                ])
                ->setBrutProb((int)$operationalRiskData['brutProb'])
                ->setNetProb((int)$operationalRiskData['netProb'])
                ->setTargetedProb((int)$operationalRiskData['targetedProb'])
                ->setCacheBrutRisk((int)$operationalRiskData['cacheBrutRisk'])
                ->setCacheNetRisk((int)$operationalRiskData['cacheNetRisk'])
                ->setCacheTargetedRisk((int)$operationalRiskData['cacheTargetedRisk'])
                ->setKindOfMeasure((int)$operationalRiskData['kindOfMeasure'])
                ->setComment($operationalRiskData['comment'] ?? '')
                ->setMitigation($operationalRiskData['mitigation'] ?? '')
                ->setIsSpecific((bool)$operationalRiskData['specific'])
                ->setContext($operationalRiskData['context'] ?? '')
                ->setCreator($this->connectedUser->getEmail());

            if (!empty($operationalRiskData['riskOwner'])) {
                $instanceRiskOwner = $this->instanceRiskOwnerService->getOrCreateInstanceRiskOwner(
                    $anr,
                    $anr,
                    $operationalRiskData['riskOwner']
                );
                $operationalInstanceRisk->setInstanceRiskOwner($instanceRiskOwner);
            }

            if ($areScalesLevelsOfLikelihoodDifferent) {
                $this->operationalRiskScaleImportProcessor->adjustOperationalRisksProbabilityScales(
                    $operationalInstanceRisk,
                    $externalOperationalRiskScalesData[OperationalRiskScaleSuperClass::TYPE_LIKELIHOOD],
                    $operationalRiskScalesData[OperationalRiskScaleSuperClass::TYPE_LIKELIHOOD]
                );
            }

            if (!empty($operationalRiskData['rolfRisk']) && $monarcObject->getRolfTag() !== null) {
                /** @var Entity\RolfRisk|null $rolfRisk */
                $rolfRisk = $this->importCacheHelper->getItemFromArrayCache(
                    'rolf_risks_by_old_ids',
                    (int)$operationalRiskData['rolfRisk']
                );
                if ($rolfRisk !== null) {
                    $operationalInstanceRisk->setRolfRisk($rolfRisk)->setRiskCacheCode($rolfRisk->getCode());
                }
            }

            $impactScaleData = $operationalRiskScalesData[OperationalRiskScaleSuperClass::TYPE_IMPACT];
            foreach ($impactScaleData['operationalRiskScaleTypes'] as $index => $scaleType) {
                /** @var Entity\OperationalRiskScaleType $operationalRiskScaleType */
                $operationalRiskScaleType = $scaleType['object'];
                $operationalInstanceRiskScale = (new Entity\OperationalInstanceRiskScale())
                    ->setAnr($anr)
                    ->setOperationalRiskScaleType($operationalRiskScaleType)
                    ->setOperationalInstanceRisk($operationalInstanceRisk)
                    ->setCreator($this->connectedUser->getEmail());

                if ($includeEval) {
                    /* The format is since v2.11.0 */
                    if (isset($operationalRiskData['scalesValues'])) {
                        $externalScaleTypeId = null;
                        if ($isImportTypeAnr) {
                            /* For anr import, match current scale type translation key with external ids. */
                            $externalScaleTypeId = $this->getExternalScaleTypeIdByCurrentScaleLabel(
                                $operationalRiskScaleType->getLabel()
                            );
                        } elseif (isset($matchedScaleTypesMap['currentScaleTypeLabelToExternalIds'][
                            $operationalRiskScaleType->getLabel()
                        ])) {
                            /* For instance import, match current scale type label with external ids. */
                            $externalScaleTypeId = $matchedScaleTypesMap['currentScaleTypeLabelToExternalIds'][
                                $operationalRiskScaleType->getLabel()
                            ];
                        }
                        if ($externalScaleTypeId !== null
                            && isset($operationalRiskData['scalesValues'][$externalScaleTypeId])
                        ) {
                            $scalesValueData = $operationalRiskData['scalesValues'][$externalScaleTypeId];
                            $operationalInstanceRiskScale->setBrutValue($scalesValueData['brutValue']);
                            $operationalInstanceRiskScale->setNetValue($scalesValueData['netValue']);
                            $operationalInstanceRiskScale->setTargetedValue($scalesValueData['targetedValue']);
                            if ($areImpactScaleTypesValuesDifferent) {
                                /* We convert from the importing new scales to the current anr scales. */
                                $this->operationalRiskScaleImportProcessor->adjustOperationalInstanceRisksScales(
                                    $operationalInstanceRiskScale,
                                    $externalOperationalRiskScalesData[OperationalRiskScaleSuperClass::TYPE_IMPACT],
                                    $impactScaleData
                                );
                            }
                        }
                        /* The format before v2.11.0. Update only first 5 scales (ROLFP if not changed by user). */
                    } elseif ($index < 5) {
                        foreach ($oldInstanceRiskFieldsMapToScaleTypesFields[$index] as $oldFiled => $typeField) {
                            $operationalInstanceRiskScale->{'set' . $typeField}($operationalRiskData[$oldFiled]);
                        }
                        if ($areImpactScaleTypesValuesDifferent) {
                            /* We convert from the importing new scales to the current anr scales. */
                            $this->operationalRiskScaleImportProcessor->adjustOperationalInstanceRisksScales(
                                $operationalInstanceRiskScale,
                                $externalOperationalRiskScalesData[OperationalRiskScaleSuperClass::TYPE_IMPACT],
                                $impactScaleData
                            );
                        }
                    }
                }

                $this->operationalInstanceRiskScaleTable->save($operationalInstanceRiskScale, false);
            }

            if (!empty($matchedScaleTypesMap['notMatchedScaleTypes']) && !$isImportTypeAnr) {
                /* In case of instance import, there is a need to create external scale types in case if
                    the linked values are set for at least one operational instance risk.
                    The new created type has to be linked with all the existed risks. */
                foreach ($matchedScaleTypesMap['notMatchedScaleTypes'] as $extScaleTypeId => $extScaleTypeData) {
                    if (isset($operationalRiskData['scalesValues'][$extScaleTypeId])) {
                        $scalesValueData = $operationalRiskData['scalesValues'][$extScaleTypeId];
                        if ($scalesValueData['netValue'] !== -1
                            || $scalesValueData['brutValue'] !== -1
                            || $scalesValueData['targetedValue'] !== -1
                        ) {
                            $operationalRiskScaleType = (new Entity\OperationalRiskScaleType())
                                ->setAnr($anr)
                                ->setOperationalRiskScale($impactScaleData['object'])
                                ->setLabel($extScaleTypeData['label'] ?? $extScaleTypeData['translation']['value'])
                                ->setCreator($this->connectedUser->getEmail());
                            $this->operationalRiskScaleTypeTable->save($operationalRiskScaleType, false);

                            foreach ($extScaleTypeData['operationalRiskScaleComments'] as $scaleCommentData) {
                                $this->operationalRiskScaleImportProcessor->createOrUpdateOperationalRiskScaleComment(
                                    $anr,
                                    false,
                                    $impactScaleData['object'],
                                    $scaleCommentData,
                                    [],
                                    $operationalRiskScaleType
                                );
                            }

                            $operationalInstanceRiskScale = (new Entity\OperationalInstanceRiskScale())
                                ->setAnr($anr)
                                ->setOperationalInstanceRisk($operationalInstanceRisk)
                                ->setOperationalRiskScaleType($operationalRiskScaleType)
                                ->setBrutValue($scalesValueData['brutValue'])
                                ->setNetValue($scalesValueData['netValue'])
                                ->setTargetedValue($scalesValueData['targetedValue'])
                                ->setCreator($this->connectedUser->getEmail());
                            $this->operationalInstanceRiskScaleTable->save($operationalInstanceRiskScale, false);

                            $this->operationalRiskScaleImportProcessor->adjustOperationalInstanceRisksScales(
                                $operationalInstanceRiskScale,
                                $externalOperationalRiskScalesData[OperationalRiskScaleSuperClass::TYPE_IMPACT],
                                $impactScaleData
                            );

                            /* To swap the scale risk between the two keys in the map as it is already matched. */
                            unset($matchedScaleTypesMap['notMatchedScaleTypes'][$extScaleTypeId]);
                            $matchedScaleTypesMap['currentScaleTypeLabelToExternalIds'][
                                $operationalRiskScaleType->getLabel()
                            ] = $extScaleTypeId;

                            /* Due to the new scale type and related comments the cached the data has to be updated. */
                            [$scaleTypesData, $commentsIndexToValueMap] = $this->operationalRiskScaleImportProcessor
                                ->prepareScaleTypesDataAndCommentsIndexToValueMap($impactScaleData['object']);
                            $this->importCacheHelper->addItemToArrayCache('current_operational_risk_scales_data', [
                                'min' => $impactScaleData['min'],
                                'max' => $impactScaleData['max'],
                                'object' => $impactScaleData['object'],
                                'commentsIndexToValueMap' => $commentsIndexToValueMap,
                                'operationalRiskScaleTypes' => $scaleTypesData,
                            ], OperationalRiskScaleSuperClass::TYPE_IMPACT);
                            $operationalRiskScalesData = $this->operationalRiskScaleImportProcessor
                                ->getCurrentOperationalRiskScalesData($anr);
                            $areImpactScaleTypesValuesDifferent = true;

                            /* Link the newly created scale type to all the existed operational risks. */
                            $operationalInstanceRisks = $this->instanceRiskOpTable->findByAnrAndInstance(
                                $anr,
                                $instance
                            );
                            foreach ($operationalInstanceRisks as $operationalInstanceRiskToUpdate) {
                                if ($operationalInstanceRiskToUpdate->getId() !== $operationalInstanceRisk->getId()) {
                                    $this->operationalInstanceRiskScaleTable->save(
                                        (new Entity\OperationalInstanceRiskScale())
                                            ->setAnr($anr)
                                            ->setOperationalInstanceRisk($operationalInstanceRiskToUpdate)
                                            ->setOperationalRiskScaleType($operationalRiskScaleType)
                                            ->setCreator($this->connectedUser->getEmail()),
                                        false
                                    );
                                }
                            }
                        }
                    }
                }
            }

            if ($includeEval) {
                /* recalculate the cached risk values */
                $this->anrInstanceRiskOpService->updateRiskCacheValues($operationalInstanceRisk);
            }

            $this->instanceRiskOpTable->save($operationalInstanceRisk, false);

            /* Process recommendations related to the operational risk. */
            if ($includeEval && !empty($data['recosop'][$operationalRiskData['id']])) {
                foreach ($data['recosop'][$operationalRiskData['id']] as $recommendationData) {
                    $recommendation = $this->recommendationImportProcessor->processRecommendationDataLinkedToRisk(
                        $anr,
                        $recommendationData,
                        $data['recSets'],
                        $operationalRiskData['kindOfMeasure'] !== InstanceRiskOpSuperClass::KIND_NOT_TREATED
                    );

                    $this->anrRecommendationRiskService->createRecommendationRisk(
                        $recommendation,
                        $operationalInstanceRisk,
                        $recommendationData['commentAfter'] ?? '',
                        false
                    );
                }
            }
        }
    }

    private function matchAndGetOperationalRiskScaleTypesMap(
        array $operationalRiskScalesData,
        array $externalOperationalRiskScalesData
    ): array {
        $matchedScaleTypesMap = [
            'currentScaleTypeLabelToExternalIds' => [],
            'notMatchedScaleTypes' => [],
        ];
        $scaleTypesData = $operationalRiskScalesData[OperationalRiskScaleSuperClass::TYPE_IMPACT][
            'operationalRiskScaleTypes'
        ];
        $externalScaleTypesData = $externalOperationalRiskScalesData[OperationalRiskScaleSuperClass::TYPE_IMPACT][
            'operationalRiskScaleTypes'
        ];
        foreach ($externalScaleTypesData as $externalScaleTypeData) {
            $isMatched = false;
            foreach ($scaleTypesData as $scaleTypeData) {
                /** @var Entity\OperationalRiskScaleType $scaleType */
                $scaleType = $scaleTypeData['object'];
                $externalLabel = $externalScaleTypeData['label'] ?? $externalScaleTypeData['translation']['value'];
                if ($externalLabel === $scaleType->getLabel()) {
                    $matchedScaleTypesMap['currentScaleTypeLabelToExternalIds'][$scaleType->getLabel()]
                        = $externalScaleTypeData['id'];
                    $isMatched = true;
                    break;
                }
            }
            if (!$isMatched) {
                $matchedScaleTypesMap['notMatchedScaleTypes'][$externalScaleTypeData['id']] = $externalScaleTypeData;
            }
        }

        return $matchedScaleTypesMap;
    }

    private function getExternalScaleTypeIdByCurrentScaleLabel(string $label): ?int
    {
        return $this->cachedData['operationalRiskScaleTypes']['currentScaleTypeLabelToExternalIds'][$label] ?? null;
    }

    private function areLikelihoodScalesLevelsOfTypeDifferent(
        array $operationalRiskScales,
        array $externalOperationalRiskScalesData
    ): bool {
        $likelihoodType = OperationalRiskScaleSuperClass::TYPE_LIKELIHOOD;
        foreach ($operationalRiskScales as $scaleType => $operationalRiskScale) {
            if ($scaleType === $likelihoodType) {
                $externalScaleDataOfType = $externalOperationalRiskScalesData[$likelihoodType];
                if ($operationalRiskScale['min'] !== $externalScaleDataOfType['min']
                    || $operationalRiskScale['max'] !== $externalScaleDataOfType['max']) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Checks if any of the scale comments values related to the scale types have different scaleValue
     * then in the new operational scale data.
     */
    private function areImpactScaleTypeValuesDifferent(
        array $operationalRiskScales,
        array $extOperationalRiskScalesData
    ): bool {
        $impactType = OperationalRiskScaleSuperClass::TYPE_IMPACT;
        foreach ($operationalRiskScales[$impactType]['commentsIndexToValueMap'] as $scaleIndex => $scaleValue) {
            if (!isset($extOperationalRiskScalesData[$impactType]['commentsIndexToValueMap'][$scaleIndex])) {
                return true;
            }
            $extScaleValue = $extOperationalRiskScalesData[$impactType]['commentsIndexToValueMap'][$scaleIndex];
            if ($scaleValue !== $extScaleValue) {
                return true;
            }
        }

        return false;
    }
}
