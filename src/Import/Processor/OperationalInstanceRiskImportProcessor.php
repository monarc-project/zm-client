<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Import\Processor;

use Monarc\Core\Entity\OperationalRiskScaleSuperClass;
use Monarc\Core\Entity\UserSuperClass;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Entity;
use Monarc\FrontOffice\Import\Helper\ImportCacheHelper;
use Monarc\FrontOffice\Import\Service\InstanceImportService;
use Monarc\FrontOffice\Service\AnrInstanceRiskOpService;
use Monarc\FrontOffice\Service\AnrRecommendationRiskService;
use Monarc\FrontOffice\Service\AnrSupervisorService;
use Monarc\FrontOffice\Table;
use PhpOffice\PhpWord\Exception\Exception;

class OperationalInstanceRiskImportProcessor
{
    private UserSuperClass $connectedUser;

    private static array $oldInstanceRiskFieldsMapToScaleTypesFields = [
        ['brutR' => 'BrutValue', 'netR' => 'NetValue', 'targetedR' => 'TargetedValue'],
        ['brutO' => 'BrutValue', 'netO' => 'NetValue', 'targetedO' => 'TargetedValue'],
        ['brutL' => 'BrutValue', 'netL' => 'NetValue', 'targetedL' => 'TargetedValue'],
        ['brutF' => 'BrutValue', 'netF' => 'NetValue', 'targetedF' => 'TargetedValue'],
        ['brutP' => 'BrutValue', 'netP' => 'NetValue', 'targetedP' => 'TargetedValue'],
    ];

    public function __construct(
        private Table\OperationalInstanceRiskScaleTable $operationalInstanceRiskScaleTable,
        private Table\OperationalRiskScaleTypeTable $operationalRiskScaleTypeTable,
        private Table\InstanceRiskOpTable $instanceRiskOpTable,
        private OperationalRiskImportProcessor $operationalRiskImportProcessor,
        private RiskSourceImportProcessor $riskSourceImportProcessor,
        private OperationalRiskScaleImportProcessor $operationalRiskScaleImportProcessor,
        private RecommendationImportProcessor $recommendationImportProcessor,
        private ImportCacheHelper $importCacheHelper,
        private AnrSupervisorService $anrSupervisorService,
        private AnrInstanceRiskOpService $anrInstanceRiskOpService,
        private AnrRecommendationRiskService $anrRecommendationRiskService,
        ConnectedUserService $connectedUserService
    ) {
        $this->connectedUser = $connectedUserService->getConnectedUser();
    }

    public function processOperationalInstanceRisksData(
        Entity\Anr $anr,
        Entity\Instance $instance,
        array $operationalInstanceRisksData
    ): void {
        $currentOperationalRiskScalesData = $this->operationalRiskScaleImportProcessor
            ->getCurrentOperationalRiskScalesData($anr);
        $externalOperationalRiskScalesData = [];
        $areScalesLevelsOfLikelihoodDifferent = false;
        $areImpactScaleTypesValuesDifferent = false;
        $matchedScaleTypesMap = [];
        $withEval = $this->importCacheHelper->getValueFromArrayCache('with_eval');
        $isImportTypeInstance = $this->importCacheHelper->getValueFromArrayCache(
            'import_type'
        ) === InstanceImportService::IMPORT_TYPE_INSTANCE;
        /* For the instances import with evaluations the values have to be converted to the current analysis scales. */
        if ($withEval && $isImportTypeInstance) {
            $externalOperationalRiskScalesData = $this->operationalRiskScaleImportProcessor
                ->getExternalOperationalRiskScalesData($anr, []);
            if (empty($externalOperationalRiskScalesData)) {
                throw new Exception('The scales have to be prepared before the process of the operational risks.', 412);
            }
            $areScalesLevelsOfLikelihoodDifferent = $this->areLikelihoodScalesLevelsOfTypeDifferent(
                $currentOperationalRiskScalesData,
                $externalOperationalRiskScalesData
            );
            $areImpactScaleTypesValuesDifferent = $this->areImpactScaleTypeValuesDifferent(
                $currentOperationalRiskScalesData,
                $externalOperationalRiskScalesData
            );
            $matchedScaleTypesMap = $this->matchAndGetOperationalRiskScaleTypesMap(
                $currentOperationalRiskScalesData,
                $externalOperationalRiskScalesData
            );
        }
        foreach ($operationalInstanceRisksData as $operationalInstanceRiskData) {
            $operationalRisk = empty($operationalInstanceRiskData['operationalRisk'])
                ? null
                : $this->operationalRiskImportProcessor->processOperationalRiskData(
                    $anr,
                    $operationalInstanceRiskData['operationalRisk']
                );
            /** @var Entity\MonarcObject $object */
            $object = $instance->getObject();
            $operationalInstanceRisk = $this->anrInstanceRiskOpService
                ->createInstanceRiskOpObject($instance, $object, $operationalRisk, $operationalInstanceRiskData)
                ->setSpecific($operationalInstanceRiskData['specific'] ?? 0);
            if (!empty($operationalInstanceRiskData['riskSource'])) {
                $operationalInstanceRisk->setRiskSource(
                    $this->riskSourceImportProcessor->processRiskSourceData($anr, $operationalInstanceRiskData['riskSource'])
                );
            }
            if ($this->importCacheHelper->getValueFromArrayCache('with_eval')) {
                $operationalInstanceRisk
                    ->setBrutProb((int)$operationalInstanceRiskData['brutProb'])
                    ->setNetProb((int)$operationalInstanceRiskData['netProb'])
                    ->setTargetedProb((int)$operationalInstanceRiskData['targetedProb'])
                    ->setCacheBrutRisk((int)$operationalInstanceRiskData['cacheBrutRisk'])
                    ->setCacheNetRisk((int)$operationalInstanceRiskData['cacheNetRisk'])
                    ->setCacheTargetedRisk((int)$operationalInstanceRiskData['cacheTargetedRisk'])
                    ->setKindOfMeasure((int)$operationalInstanceRiskData['kindOfMeasure'])
                    ->setComment($operationalInstanceRiskData['comment'] ?? '')
                    ->setMitigation($operationalInstanceRiskData['mitigation'] ?? '')
                    ->setContext($operationalInstanceRiskData['context'] ?? '');
                $riskOwnerSupervisor = $operationalInstanceRiskData['riskOwnerSupervisor']
                    ?? $operationalInstanceRiskData['risk_owner_supervisor']
                    ?? null;
                if (!empty($riskOwnerSupervisor)
                    && is_array($riskOwnerSupervisor)
                ) {
                    $this->anrSupervisorService->assignRiskOwnerSupervisorData(
                        $anr,
                        $riskOwnerSupervisor,
                        $operationalInstanceRisk
                    );
                }
                if (array_key_exists('lastReviewDate', $operationalInstanceRiskData)) {
                    $operationalInstanceRisk->setLastReviewDate(
                        empty($operationalInstanceRiskData['lastReviewDate'])
                            ? null
                            : (\DateTime::createFromFormat('Y-m-d', $operationalInstanceRiskData['lastReviewDate']) ?: null)
                    );
                }
                if (array_key_exists('reviewFrequency', $operationalInstanceRiskData)) {
                    $reviewFrequency = trim((string)$operationalInstanceRiskData['reviewFrequency']);
                    $operationalInstanceRisk->setReviewFrequency($reviewFrequency === '' ? null : $reviewFrequency);
                }
                if (array_key_exists('residualRiskDecision', $operationalInstanceRiskData)) {
                    $operationalInstanceRisk->setResidualRiskDecision(
                        $this->normalizeResidualRiskDecision($operationalInstanceRiskData['residualRiskDecision'])
                    );
                }
                if (array_key_exists('residualAcceptanceUseRiskOwner', $operationalInstanceRiskData)) {
                    $operationalInstanceRisk->setResidualAcceptanceUseRiskOwner(
                        (bool)$operationalInstanceRiskData['residualAcceptanceUseRiskOwner']
                    );
                }
                $residualAcceptanceApproverSupervisor = $operationalInstanceRiskData['residualAcceptanceApproverSupervisor'] ?? null;
                if (!empty($residualAcceptanceApproverSupervisor) && is_array($residualAcceptanceApproverSupervisor)) {
                    $operationalInstanceRisk->setResidualAcceptanceApproverSupervisor(
                        $this->anrSupervisorService->getOrCreateSupervisor(
                            $anr,
                            $residualAcceptanceApproverSupervisor['name'] ?? null,
                            $residualAcceptanceApproverSupervisor['email'] ?? null,
                            [Entity\AnrSupervisorRole::ROLE_RESIDUAL_RISK_APPROVER]
                        )
                    );
                }
                if (array_key_exists('residualRiskDecidedAt', $operationalInstanceRiskData)) {
                    $operationalInstanceRisk->setResidualRiskDecidedAt(
                        empty($operationalInstanceRiskData['residualRiskDecidedAt'])
                            ? null
                            : (\DateTime::createFromFormat('Y-m-d', $operationalInstanceRiskData['residualRiskDecidedAt']) ?: null)
                    );
                }
                if (array_key_exists('residualAcceptancePerformedByName', $operationalInstanceRiskData)) {
                    $performedByName = trim((string)$operationalInstanceRiskData['residualAcceptancePerformedByName']);
                    $operationalInstanceRisk->setResidualAcceptancePerformedByName(
                        $performedByName === '' ? null : $performedByName
                    );
                }
                if (array_key_exists('residualAcceptancePerformedByEmail', $operationalInstanceRiskData)) {
                    $performedByEmail = trim((string)$operationalInstanceRiskData['residualAcceptancePerformedByEmail']);
                    $operationalInstanceRisk->setResidualAcceptancePerformedByEmail(
                        $performedByEmail === '' ? null : $performedByEmail
                    );
                }
                if (array_key_exists('residualAcceptancePerformedOnBehalf', $operationalInstanceRiskData)) {
                    $operationalInstanceRisk->setResidualAcceptancePerformedOnBehalf(
                        (bool)$operationalInstanceRiskData['residualAcceptancePerformedOnBehalf']
                    );
                }
                if (array_key_exists('residualRiskJustification', $operationalInstanceRiskData)) {
                    $justification = trim((string)$operationalInstanceRiskData['residualRiskJustification']);
                    $operationalInstanceRisk->setResidualRiskJustification($justification === '' ? null : $justification);
                }
                $residualRiskAcceptance = $operationalInstanceRiskData['residualRiskAcceptance']
                    ?? $operationalInstanceRiskData['residual_risk_acceptance']
                    ?? null;
                if (!empty($residualRiskAcceptance)
                    && is_array($residualRiskAcceptance)
                ) {
                    $operationalInstanceRisk->setResidualRiskDecision(
                        $this->normalizeResidualRiskDecision($residualRiskAcceptance['decision'] ?? null)
                    );
                    if (array_key_exists('performedByName', $residualRiskAcceptance)) {
                        $performedByName = trim((string)$residualRiskAcceptance['performedByName']);
                        $operationalInstanceRisk->setResidualAcceptancePerformedByName(
                            $performedByName === '' ? null : $performedByName
                        );
                    }
                    if (array_key_exists('performedByEmail', $residualRiskAcceptance)) {
                        $performedByEmail = trim((string)$residualRiskAcceptance['performedByEmail']);
                        $operationalInstanceRisk->setResidualAcceptancePerformedByEmail(
                            $performedByEmail === '' ? null : $performedByEmail
                        );
                    }
                    if (array_key_exists('performedOnBehalf', $residualRiskAcceptance)) {
                        $operationalInstanceRisk->setResidualAcceptancePerformedOnBehalf(
                            (bool)$residualRiskAcceptance['performedOnBehalf']
                        );
                    }
                    $approverData = $residualRiskAcceptance['approver'] ?? null;
                    $legacyDecidedBy = trim((string)($residualRiskAcceptance['decidedBy'] ?? ''));
                    $legacyDecidedByEmail = trim((string)($residualRiskAcceptance['decidedByEmail'] ?? ''));
                    if (empty($approverData) && ($legacyDecidedBy !== '' || $legacyDecidedByEmail !== '')) {
                        $approverData = [
                            'name' => $legacyDecidedBy !== '' ? $legacyDecidedBy : null,
                            'email' => $legacyDecidedByEmail !== '' ? $legacyDecidedByEmail : null,
                        ];
                    }
                    if (!empty($approverData) && is_array($approverData)) {
                        $approverSupervisor = !empty($riskOwnerSupervisor)
                            && is_array($riskOwnerSupervisor)
                            && (($riskOwnerSupervisor['name'] ?? '') === ($approverData['name'] ?? '')
                                || ($riskOwnerSupervisor['email'] ?? '') === ($approverData['email'] ?? ''))
                            ? $operationalInstanceRisk->getRiskOwnerSupervisor()
                            : $this->anrSupervisorService->getOrCreateSupervisor(
                                $anr,
                                $approverData['name'] ?? null,
                                $approverData['email'] ?? null,
                                [Entity\AnrSupervisorRole::ROLE_RESIDUAL_RISK_APPROVER]
                            );
                        $operationalInstanceRisk->setResidualAcceptanceApproverSupervisor($approverSupervisor)
                            ->setResidualRiskDecidedBySupervisor($approverSupervisor);
                    }
                    if (!empty($residualRiskAcceptance['date'])) {
                        $date = \DateTime::createFromFormat('Y-m-d', (string)$residualRiskAcceptance['date']) ?: null;
                        $operationalInstanceRisk->setResidualRiskDecidedAt($date);
                    }
                    if (array_key_exists('justification', $residualRiskAcceptance)) {
                        $justification = trim((string)$residualRiskAcceptance['justification']);
                        $operationalInstanceRisk->setResidualRiskJustification($justification === '' ? null : $justification);
                    }
                }
            }
            if ($areScalesLevelsOfLikelihoodDifferent) {
                $this->operationalRiskScaleImportProcessor->adjustOperationalRisksProbabilityScales(
                    $operationalInstanceRisk,
                    $externalOperationalRiskScalesData[OperationalRiskScaleSuperClass::TYPE_LIKELIHOOD],
                    $currentOperationalRiskScalesData[OperationalRiskScaleSuperClass::TYPE_LIKELIHOOD]
                );
            }

            [$currentOperationalRiskScalesData, $areImpactScaleTypesValuesDifferent] = $this
                ->processOperationalRiskScalesTypes(
                    $anr,
                    $instance,
                    $operationalInstanceRisk,
                    $currentOperationalRiskScalesData,
                    $externalOperationalRiskScalesData,
                    $matchedScaleTypesMap,
                    $operationalInstanceRiskData,
                    $areImpactScaleTypesValuesDifferent
                );

            if ($withEval) {
                /* Recalculate the cached risk values. */
                $this->anrInstanceRiskOpService->updateRiskCacheValues($operationalInstanceRisk);
            }

            /* Process the linked recommendations. */
            foreach ($operationalInstanceRiskData['recommendations'] ?? [] as $recommendationData) {
                $recommendationSet = $this->recommendationImportProcessor
                    ->processRecommendationSetData($anr, $recommendationData['recommendationSet']);
                $recommendation = $this->recommendationImportProcessor
                    ->processRecommendationData($recommendationSet, $recommendationData);
                $this->anrRecommendationRiskService->createRecommendationRisk(
                    $recommendation,
                    $operationalInstanceRisk,
                    $recommendationData['commentAfter'] ?? '',
                    false
                );
            }

            $this->instanceRiskOpTable->save($operationalInstanceRisk, false);
        }
    }

    private function normalizeResidualRiskDecision(mixed $decision): ?string
    {
        $decision = mb_strtolower(trim((string)$decision));

        return match ($decision) {
            '', null => null,
            'accepted' => 'accepted',
            'rejected', 'not_accepted' => 'not_accepted',
            default => $decision,
        };
    }

    private function processOperationalRiskScalesTypes(
        Entity\Anr $anr,
        Entity\Instance $instance,
        Entity\InstanceRiskOp $operationalInstanceRisk,
        array $currentOperationalRiskScalesData,
        array $externalOperationalRiskScalesData,
        array $matchedScaleTypesMap,
        array $operationalInstanceRiskData,
        bool $areImpactScaleTypesValuesDifferent
    ): array {
        $withEval = $this->importCacheHelper->getValueFromArrayCache('with_eval');
        $isImportTypeAnr = $this->importCacheHelper
            ->getValueFromArrayCache('import_type') === InstanceImportService::IMPORT_TYPE_ANR;

        $currentImpactScaleData = $currentOperationalRiskScalesData[OperationalRiskScaleSuperClass::TYPE_IMPACT];
        foreach ($currentImpactScaleData['operationalRiskScaleTypes'] as $index => $scaleType) {
            /** @var Entity\OperationalRiskScaleType $operationalRiskScaleType */
            $operationalRiskScaleType = $scaleType['object'];
            $operationalInstanceRiskScale = (new Entity\OperationalInstanceRiskScale())
                ->setAnr($anr)
                ->setOperationalRiskScaleType($operationalRiskScaleType)
                ->setOperationalInstanceRisk($operationalInstanceRisk)
                ->setCreator($this->connectedUser->getEmail());

            if ($withEval) {
                /* The format is since v2.11.0 */
                if (isset($operationalInstanceRiskData['operationalInstanceRiskScales'])) {
                    $externalScaleTypeId = null;
                    if ($isImportTypeAnr) {
                        /* For anr import, match current scale type label with external ids. */
                        $externalScaleTypeId = $this->importCacheHelper->getItemFromArrayCache(
                            'operational_risk_scale_type_label_to_old_id',
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
                        && isset($operationalInstanceRiskData['operationalInstanceRiskScales'][$externalScaleTypeId])
                    ) {
                        $scalesValueData = $operationalInstanceRiskData['operationalInstanceRiskScales'][
                            $externalScaleTypeId
                        ];
                        $operationalInstanceRiskScale->setBrutValue((int)$scalesValueData['brutValue']);
                        $operationalInstanceRiskScale->setNetValue((int)$scalesValueData['netValue']);
                        $operationalInstanceRiskScale->setTargetedValue((int)$scalesValueData['targetedValue']);
                        if ($areImpactScaleTypesValuesDifferent) {
                            /* We convert from the importing new scales to the current anr scales. */
                            $this->operationalRiskScaleImportProcessor->adjustOperationalInstanceRisksScales(
                                $operationalInstanceRiskScale,
                                $externalOperationalRiskScalesData[OperationalRiskScaleSuperClass::TYPE_IMPACT],
                                $currentImpactScaleData
                            );
                        }
                    }
                    /* The format before v2.11.0. Update only first 5 scales (ROLFP if not changed by user). */
                } elseif ($index < 5) {
                    foreach (static::$oldInstanceRiskFieldsMapToScaleTypesFields[$index] as $oldFiled => $typeField) {
                        $operationalInstanceRiskScale->{'set' . $typeField}((int)$operationalInstanceRiskData[$oldFiled]);
                    }
                    if ($areImpactScaleTypesValuesDifferent) {
                        /* We convert from the importing new scales to the current anr scales. */
                        $this->operationalRiskScaleImportProcessor->adjustOperationalInstanceRisksScales(
                            $operationalInstanceRiskScale,
                            $externalOperationalRiskScalesData[OperationalRiskScaleSuperClass::TYPE_IMPACT],
                            $currentImpactScaleData
                        );
                    }
                }
            }

            $this->operationalInstanceRiskScaleTable->save($operationalInstanceRiskScale, false);
        }

        /* Process not matched operational risk scales types. */
        if (!empty($matchedScaleTypesMap['notMatchedScaleTypes']) && !$isImportTypeAnr) {
            /* In case of instance import, there is a need to create external scale types in case if
                the linked values are set for at least one operational instance risk.
                The new created type has to be linked with all the existed risks. */
            foreach ($matchedScaleTypesMap['notMatchedScaleTypes'] as $extScaleTypeId => $extScaleTypeData) {
                if (isset($operationalRiskData['operationalInstanceRiskScales'][$extScaleTypeId])) {
                    $scalesValueData = $operationalRiskData['operationalInstanceRiskScales'][$extScaleTypeId];
                    if ($scalesValueData['netValue'] !== -1
                        || $scalesValueData['brutValue'] !== -1
                        || $scalesValueData['targetedValue'] !== -1
                    ) {
                        $operationalRiskScaleType = (new Entity\OperationalRiskScaleType())
                            ->setAnr($anr)
                            ->setOperationalRiskScale($currentImpactScaleData['object'])
                            ->setLabel($extScaleTypeData['label'] ?? $extScaleTypeData['translation']['value'])
                            ->setCreator($this->connectedUser->getEmail());
                        $this->operationalRiskScaleTypeTable->save($operationalRiskScaleType, false);

                        foreach ($extScaleTypeData['operationalRiskScaleComments'] as $scaleCommentData) {
                            $this->operationalRiskScaleImportProcessor->createOrUpdateOperationalRiskScaleComment(
                                $anr,
                                false,
                                $currentImpactScaleData['object'],
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
                            $currentImpactScaleData
                        );

                        /* To swap the scale risk between the two keys in the map as it is already matched. */
                        unset($matchedScaleTypesMap['notMatchedScaleTypes'][$extScaleTypeId]);
                        $matchedScaleTypesMap['currentScaleTypeLabelToExternalIds'][$operationalRiskScaleType
                            ->getLabel()] = $extScaleTypeId;

                        /* Due to the new scale type and related comments the cached the data has to be updated. */
                        [$scaleTypesData, $commentsIndexToValueMap] = $this->operationalRiskScaleImportProcessor
                            ->prepareScaleTypesDataAndCommentsIndexToValueMap($currentImpactScaleData['object']);
                        $this->importCacheHelper->addItemToArrayCache('current_operational_risk_scales_data', [
                            'min' => $currentImpactScaleData['min'],
                            'max' => $currentImpactScaleData['max'],
                            'object' => $currentImpactScaleData['object'],
                            'commentsIndexToValueMap' => $commentsIndexToValueMap,
                            'operationalRiskScaleTypes' => $scaleTypesData,
                        ], OperationalRiskScaleSuperClass::TYPE_IMPACT);
                        $currentOperationalRiskScalesData = $this->operationalRiskScaleImportProcessor
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

        return [$currentOperationalRiskScalesData, $areImpactScaleTypesValuesDifferent];
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
