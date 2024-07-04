<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Import\Processor;

use Monarc\Core\Entity\ScaleSuperClass;
use Monarc\Core\Entity\UserSuperClass;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\Core\Service\Traits\RiskCalculationTrait;
use Monarc\FrontOffice\Entity;
use Monarc\FrontOffice\Import\Helper\ImportCacheHelper;
use Monarc\FrontOffice\Import\Traits\EvaluationConverterTrait;
use Monarc\FrontOffice\Service;
use Monarc\FrontOffice\Table;

class ScaleImportProcessor
{
    use RiskCalculationTrait;
    use EvaluationConverterTrait;

    private UserSuperClass $connectedUser;

    public function __construct(
        private Table\ScaleTable $scaleTable,
        private Table\ScaleImpactTypeTable $scaleImpactTypeTable,
        private Table\ScaleCommentTable $scaleCommentTable,
        private Table\InstanceTable $instanceTable,
        private Table\InstanceRiskTable $instanceRiskTable,
        private Table\InstanceConsequenceTable $instanceConsequenceTable,
        private Table\ThreatTable $threatTable,
        private ImportCacheHelper $importCacheHelper,
        private Service\AnrScaleService $anrScaleService,
        private Service\AnrScaleImpactTypeService $anrScaleImpactTypeService,
        private Service\AnrScaleCommentService $anrScaleCommentService,
        ConnectedUserService $connectedUserService
    ) {
        $this->connectedUser = $connectedUserService->getConnectedUser();
    }

    public function applyNewScalesFromData(Entity\Anr $anr, array $newScalesData): void
    {
        $scalesDiff = [];
        /** @var Entity\Scale $scale */
        foreach ($this->scaleTable->findByAnr($anr) as $scale) {
            /* Update the scales impact types and comments. */
            if (!empty($newScalesData[ScaleSuperClass::TYPE_IMPACT]['scaleImpactTypes'])) {
                $this->applyNewScaleImpactTypesAndComments($anr, $scale, $newScalesData['scaleImpactTypes']);
            }

            if ($scale->isScaleRangeDifferentFromData($newScalesData)) {
                if ($scale->getType() === ScaleSuperClass::TYPE_IMPACT) {
                    /* All the instance's risks and consequences values have to be updated. */
                    $scalesDiff[ScaleSuperClass::TYPE_IMPACT] = [
                        'currentRange' => ['min' => $scale->getMin(), 'max', $scale->getMax()],
                        'newRange' => [
                            'min' => $newScalesData[ScaleSuperClass::TYPE_IMPACT]['min'],
                            'max' => $newScalesData[ScaleSuperClass::TYPE_IMPACT]['max'],
                        ],
                    ];
                }

                if ($scale->getType() === ScaleSuperClass::TYPE_THREAT) {
                    /* All the threats rates' values and qualification have to be updated. */
                    $this->updateThreatsQualification($anr, $scale, $newScalesData[ScaleSuperClass::TYPE_THREAT]);

                    $scalesDiff[ScaleSuperClass::TYPE_THREAT] = [
                        'currentRange' => ['min' => $scale->getMin(), 'max', $scale->getMax()],
                        'newRange' => [
                            'min' => $newScalesData[ScaleSuperClass::TYPE_THREAT]['min'],
                            'max' => $newScalesData[ScaleSuperClass::TYPE_THREAT]['max'],
                        ],
                    ];
                }

                if ($scale->getType() === ScaleSuperClass::TYPE_VULNERABILITY) {
                    /* All the vulnerabilities' risks values have to be updated. */
                    $scalesDiff[ScaleSuperClass::TYPE_VULNERABILITY] = [
                        'currentRange' => ['min' => $scale->getMin(), 'max', $scale->getMax()],
                        'newRange' => [
                            'min' => $newScalesData[ScaleSuperClass::TYPE_VULNERABILITY]['min'],
                            'max' => $newScalesData[ScaleSuperClass::TYPE_VULNERABILITY]['max'],
                        ],
                    ];
                }

                $scale->setMin($newScalesData[$scale->getType()]['min'])
                    ->setMax($newScalesData[$scale->getType()]['max'])
                    ->setUpdater($this->connectedUser->getEmail());
                $this->scaleTable->save($scale, false);

                $this->importCacheHelper->addItemToArrayCache(
                    'scales_ranges',
                    ['min' => $scale->getMin(), 'max' => $scale->getMax()],
                    $scale->getType()
                );
            }
        }

        /* If any type of the scale has difference in range than the risks and other values have to be updated. */
        $this->updateInstancesConsequencesThreatsVulnerabilitiesAndRisks($anr, $scalesDiff);
    }

    public function updateInstancesConsequencesThreatsVulnerabilitiesAndRisks(Entity\Anr $anr, array $scalesDiff): void
    {
        if (empty($scalesDiff)) {
            return;
        }

        if (isset($scalesDiff[ScaleSuperClass::TYPE_IMPACT])) {
            /** @var Entity\InstanceConsequence $consequence */
            foreach ($this->instanceConsequenceTable->findByAnr($anr) as $consequence) {
                $consequence->setConfidentiality(
                    $consequence->isHidden() ? -1 : $this->convertValueWithinNewScalesRange(
                        $consequence->getConfidentiality(),
                        $scalesDiff[ScaleSuperClass::TYPE_IMPACT]['currentRange']['min'],
                        $scalesDiff[ScaleSuperClass::TYPE_IMPACT]['currentRange']['max'],
                        $scalesDiff[ScaleSuperClass::TYPE_IMPACT]['newRange']['min'],
                        $scalesDiff[ScaleSuperClass::TYPE_IMPACT]['newRange']['max']
                    )
                );
                $consequence->setIntegrity(
                    $consequence->isHidden() ? -1 : $this->convertValueWithinNewScalesRange(
                        $consequence->getIntegrity(),
                        $scalesDiff[ScaleSuperClass::TYPE_IMPACT]['currentRange']['min'],
                        $scalesDiff[ScaleSuperClass::TYPE_IMPACT]['currentRange']['max'],
                        $scalesDiff[ScaleSuperClass::TYPE_IMPACT]['newRange']['min'],
                        $scalesDiff[ScaleSuperClass::TYPE_IMPACT]['newRange']['max']
                    )
                );
                $consequence->setAvailability(
                    $consequence->isHidden() ? -1 : $this->convertValueWithinNewScalesRange(
                        $consequence->getAvailability(),
                        $scalesDiff[ScaleSuperClass::TYPE_IMPACT]['currentRange']['min'],
                        $scalesDiff[ScaleSuperClass::TYPE_IMPACT]['currentRange']['max'],
                        $scalesDiff[ScaleSuperClass::TYPE_IMPACT]['newRange']['min'],
                        $scalesDiff[ScaleSuperClass::TYPE_IMPACT]['newRange']['max']
                    )
                );

                $this->instanceConsequenceTable->save($consequence, false);
            }
        }

        /** @var Entity\Instance $instance */
        foreach ($this->instanceTable->findByAnr($anr) as $instance) {
            if (isset($scalesDiff[ScaleSuperClass::TYPE_IMPACT])) {
                if ($instance->getConfidentiality() !== -1) {
                    $instance->setConfidentiality($this->convertValueWithinNewScalesRange(
                        $instance->getConfidentiality(),
                        $scalesDiff[ScaleSuperClass::TYPE_IMPACT]['currentRange']['min'],
                        $scalesDiff[ScaleSuperClass::TYPE_IMPACT]['currentRange']['max'],
                        $scalesDiff[ScaleSuperClass::TYPE_IMPACT]['newRange']['min'],
                        $scalesDiff[ScaleSuperClass::TYPE_IMPACT]['newRange']['max']
                    ));
                }
                if ($instance->getIntegrity() !== -1) {
                    $instance->setIntegrity($this->convertValueWithinNewScalesRange(
                        $instance->getIntegrity(),
                        $scalesDiff[ScaleSuperClass::TYPE_IMPACT]['currentRange']['min'],
                        $scalesDiff[ScaleSuperClass::TYPE_IMPACT]['currentRange']['max'],
                        $scalesDiff[ScaleSuperClass::TYPE_IMPACT]['newRange']['min'],
                        $scalesDiff[ScaleSuperClass::TYPE_IMPACT]['newRange']['max']
                    ));
                }
                if ($instance->getAvailability() !== -1) {
                    $instance->setAvailability($this->convertValueWithinNewScalesRange(
                        $instance->getAvailability(),
                        $scalesDiff[ScaleSuperClass::TYPE_IMPACT]['currentRange']['min'],
                        $scalesDiff[ScaleSuperClass::TYPE_IMPACT]['currentRange']['max'],
                        $scalesDiff[ScaleSuperClass::TYPE_IMPACT]['newRange']['min'],
                        $scalesDiff[ScaleSuperClass::TYPE_IMPACT]['newRange']['max']
                    ));
                }

                $this->instanceTable->save($instance, false);
            }

            if (isset($scalesDiff[ScaleSuperClass::TYPE_THREAT])
                || isset($scalesDiff[ScaleSuperClass::TYPE_VULNERABILITY])
            ) {
                foreach ($instance->getInstanceRisks() as $instanceRisk) {
                    if (isset($scalesDiff[ScaleSuperClass::TYPE_THREAT])) {
                        $instanceRisk->setThreatRate($this->convertValueWithinNewScalesRange(
                            $instanceRisk->getThreatRate(),
                            $scalesDiff[ScaleSuperClass::TYPE_THREAT]['currentRange']['min'],
                            $scalesDiff[ScaleSuperClass::TYPE_THREAT]['currentRange']['max'],
                            $scalesDiff[ScaleSuperClass::TYPE_THREAT]['newRange']['min'],
                            $scalesDiff[ScaleSuperClass::TYPE_THREAT]['newRange']['max']
                        ));
                    }
                    if (isset($scalesDiff[ScaleSuperClass::TYPE_VULNERABILITY])) {
                        $oldVulRate = $instanceRisk->getVulnerabilityRate();
                        $instanceRisk->setVulnerabilityRate($this->convertValueWithinNewScalesRange(
                            $instanceRisk->getVulnerabilityRate(),
                            $scalesDiff[ScaleSuperClass::TYPE_VULNERABILITY]['currentRange']['min'],
                            $scalesDiff[ScaleSuperClass::TYPE_VULNERABILITY]['currentRange']['max'],
                            $scalesDiff[ScaleSuperClass::TYPE_VULNERABILITY]['newRange']['min'],
                            $scalesDiff[ScaleSuperClass::TYPE_VULNERABILITY]['newRange']['max']
                        ));
                        $newVulRate = $instanceRisk->getVulnerabilityRate();
                        if ($instanceRisk->getReductionAmount() !== 0) {
                            $instanceRisk->setReductionAmount($this->convertValueWithinNewScalesRange(
                                $instanceRisk->getReductionAmount(),
                                0,
                                $oldVulRate,
                                0,
                                $newVulRate,
                                0
                            ));
                        }
                    }

                    $this->recalculateRiskRates($instanceRisk);

                    $this->instanceRiskTable->save($instanceRisk, false);
                }
            }
        }
    }

    public function updateThreatsQualification(
        Entity\Anr $anr,
        Entity\Scale $currentScale,
        array $newScaleImpactData
    ): void {
        /** @var Entity\Threat $threat */
        foreach ($this->threatTable->findByAnr($anr) as $threat) {
            $threat->setQualification($this->convertValueWithinNewScalesRange(
                $threat->getQualification(),
                $currentScale->getMin(),
                $currentScale->getMax(),
                $newScaleImpactData['min'],
                $newScaleImpactData['max']
            ));
            $this->threatTable->save($threat, false);
        }
    }

    /**
     * @return array [(int)scaleType => ['min' => int, max => int]], scaleType is one of Scale::getAvailableTypes.
     */
    public function getScalesRangesFromCache(Entity\Anr $anr, int $type = null): array
    {
        if (!$this->importCacheHelper->isCacheKeySet('scales_ranges')) {
            /** @var Entity\Scale $scale */
            foreach ($this->scaleTable->findByAnr($anr) as $scale) {
                $this->importCacheHelper->addItemToArrayCache(
                    'scales_ranges',
                    ['min' => $scale->getMin(), 'max' => $scale->getMax()],
                    $scale->getType()
                );
            }
        }

        return $this->importCacheHelper->getItemFromArrayCache('scales_ranges', $type);
    }

    public function getScalesImpactTypesFromCacheByLabel(Entity\Anr $anr, string $typeLabel): ?Entity\ScaleImpactType
    {
        if (!$this->importCacheHelper->isCacheKeySet('scale_impact_types_by_label')) {
            /** @var Entity\ScaleImpactType $scaleImpactType */
            foreach ($this->scaleImpactTypeTable->findByAnr($anr) as $scaleImpactType) {
                $this->importCacheHelper->addItemToArrayCache(
                    'scale_impact_types_by_label',
                    $scaleImpactType,
                    $scaleImpactType->getLabel($anr->getLanguage())
                );
            }
        }

        return $this->importCacheHelper->getItemFromArrayCache('scale_impact_types_by_label', $typeLabel);
    }

    private function applyNewScaleImpactTypesAndComments(
        Entity\Anr $anr,
        Entity\Scale $scale,
        array $newScaleImpactTypesData
    ): void {
        $existingImpactTypesNumber = 0;
        /* Process the existing scales impact types in order to update labels and visibility if necessary. */
        foreach ($scale->getScaleImpactTypes() as $scaleImpactType) {
            $typeKey = array_search(
                $scaleImpactType->getType(),
                array_column($newScaleImpactTypesData, 'type'),
                true
            );
            if ($scaleImpactType->isSys()) {
                if ($typeKey !== false && isset($newScaleImpactTypesData[$typeKey]['label']) && (
                        $newScaleImpactTypesData[$typeKey]['label'] !== $scaleImpactType->getLabel($anr->getLanguage())
                        || $newScaleImpactTypesData[$typeKey]['isHidden'] !== $scaleImpactType->isHidden()
                    )) {
                    /* The current system impact type visibility is changing to hidden,
                    so all the consequences have to be updated and the risks recalculated if needed. */
                    if ($newScaleImpactTypesData[$typeKey]['isHidden'] && !$scaleImpactType->isHidden()) {
                        $this->validateConsequencesAndRisksForHidingImpactType($scaleImpactType);
                    }

                    $scaleImpactType->setLabels(
                        ['label' . $anr->getLanguage() => $newScaleImpactTypesData[$typeKey]['label']]
                    )->setIsHidden($newScaleImpactTypesData[$typeKey]['isHidden'])
                        ->setUpdater($this->connectedUser->getEmail());
                    $this->scaleImpactTypeTable->save($scaleImpactType, false);
                }
            }

            $this->importCacheHelper->addItemToArrayCache(
                'scale_impact_types_by_label',
                $scaleImpactType,
                $scaleImpactType->getLabel($anr->getLanguage())
            );
            $existingImpactTypesNumber++;

            if ($typeKey !== false && !empty($newScaleImpactTypesData[$typeKey]['scaleComments'])) {
                $this->applyNewScalesImpactsTypesComments(
                    $anr,
                    $scaleImpactType,
                    $newScaleImpactTypesData[$typeKey]['scaleComments']
                );
            }
        }

        /* Process the importing scale impact types data.  */
        foreach ($newScaleImpactTypesData as $newScaleImpactTypeData) {
            if (!$newScaleImpactTypeData['isSys'] && !empty($newScaleImpactTypeData['label'])) {
                /** @var Entity\ScaleImpactType $existingCustomImpactType */
                $existingCustomImpactType = $this->importCacheHelper->getItemFromArrayCache(
                    'scale_impact_types_by_label',
                    $newScaleImpactTypeData['label']
                );
                if ($existingCustomImpactType !== null) {
                    if ($existingCustomImpactType->isHidden() !== $newScaleImpactTypeData['isHidden']) {
                        if ($newScaleImpactTypesData['isHidden'] && !$existingCustomImpactType->isHidden()) {
                            $this->validateConsequencesAndRisksForHidingImpactType($existingCustomImpactType);
                        }
                        $existingCustomImpactType->setIsHidden($newScaleImpactTypeData['isHidden'])
                            ->setUpdater($this->connectedUser->getEmail());
                        $this->scaleImpactTypeTable->save($existingCustomImpactType, false);
                    }
                } else {
                    $newScaleImpactType = $this->anrScaleImpactTypeService->create($anr, array_merge(
                        $newScaleImpactTypeData,
                        ['scale' => $scale, 'type' => ++$existingImpactTypesNumber]
                    ), false);

                    $this->importCacheHelper->addItemToArrayCache(
                        'scale_impact_types_by_label',
                        $newScaleImpactType,
                        $newScaleImpactTypeData['label']
                    );

                    $this->applyNewScalesImpactsTypesComments(
                        $anr,
                        $newScaleImpactType,
                        $newScaleImpactTypeData['scaleComments']
                    );
                }
            }
        }
    }

    private function applyNewScalesImpactsTypesComments(
        Entity\Anr $anr,
        Entity\ScaleImpactType $scaleImpactType,
        array $newScaleCommentsData
    ): void {
        $langIndex = $anr->getLanguage();
        foreach ($scaleImpactType->getScaleComments() as $existingScaleComment) {
            $commentKey = array_search(
                $existingScaleComment->getScaleValue(),
                array_column($newScaleCommentsData, 'scaleValue'),
                true
            );
            if ($commentKey !== false) {
                if (!empty($newScaleCommentsData[$commentKey]['comment'])
                    && $existingScaleComment->getComment($langIndex) !== $newScaleCommentsData[$commentKey]['comment']
                ) {
                    $existingScaleComment->setComments(
                        ['comment' . $langIndex => $newScaleCommentsData[$commentKey]['comment']]
                    )->setUpdater($this->connectedUser->getEmail());
                    $this->scaleCommentTable->save($existingScaleComment, false);
                }
                unset($newScaleCommentsData[$commentKey]);
            }
        }

        foreach ($newScaleCommentsData as $newScaleCommentData) {
            $newScaleCommentData['comment' . $langIndex] = $newScaleCommentData['comment'];
            $newScaleCommentData['scale'] = $scaleImpactType->getScale();
            $newScaleCommentData['scaleImpactType'] = $scaleImpactType;
            $this->anrScaleCommentService->create($anr, $newScaleCommentData, false);
        }
    }

    private function validateConsequencesAndRisksForHidingImpactType(Entity\ScaleImpactType $hidingImpactType): void
    {
        foreach ($hidingImpactType->getInstanceConsequences() as $consequence) {
            $confidentialityOfHiding = $consequence->getConfidentiality();
            $integrityOfHiding = $consequence->getIntegrity();
            $availabilityOfHiding = $consequence->getAvailability();
            $consequence->setIsHidden(true)
                ->setConfidentiality(-1)
                ->setIntegrity(-1)
                ->setAvailability(-1)
                ->setUpdater($this->connectedUser->getEmail());
            $this->instanceConsequenceTable->save($consequence, false);

            if (max($confidentialityOfHiding, $integrityOfHiding, $availabilityOfHiding) !== -1) {
                $maxConfidentiality = -1;
                $maxIntegrity = -1;
                $maxAvailability = -1;
                $validatingInstance = $consequence->getInstance();
                foreach ($validatingInstance->getInstanceConsequences() as $consequenceToValidate) {
                    if ($consequenceToValidate->getId() !== $consequence->getId()) {
                        $maxConfidentiality = $consequenceToValidate->getConfidentiality() > $maxConfidentiality
                            ? $consequenceToValidate->getConfidentiality()
                            : $maxConfidentiality;
                        $maxIntegrity = $consequenceToValidate->getIntegrity() > $maxIntegrity
                            ? $consequenceToValidate->getIntegrity()
                            : $maxIntegrity;
                        $maxAvailability = $consequenceToValidate->getAvailability() > $maxAvailability
                            ? $consequenceToValidate->getAvailability()
                            : $maxAvailability;
                    }
                }
                if ($confidentialityOfHiding > $maxConfidentiality
                    || $integrityOfHiding > $maxIntegrity
                    || $availabilityOfHiding > $maxAvailability
                ) {
                    /* Recalculate the instances and instance risks values. */
                    $validatingInstance->setConfidentiality($maxConfidentiality)
                        ->setIntegrity($maxIntegrity)
                        ->setAvailability($maxAvailability)
                        ->setUpdater($this->connectedUser->getEmail());
                    $this->instanceTable->save($validatingInstance, false);
                    foreach ($validatingInstance->getInstanceRisks() as $validatingInstanceRisk) {
                        $this->recalculateRiskRates($validatingInstanceRisk);
                        $validatingInstanceRisk->setUpdater($this->connectedUser->getEmail());
                        $this->instanceRiskTable->save($validatingInstanceRisk, false);
                    }
                }
            }
        }
    }

    /* Is used for the imports of data exported prior v2.13.1 */
    public function updateScalesAndComments(Entity\Anr $anr, array $data): void
    {
        $scalesByType = [];
        $scalesRangesByType = [];
        /** @var Entity\Scale $scale */
        foreach ($this->scaleTable->findByAnr($anr) as $scale) {
            if (isset($data['scales'][$scale->getType()])) {
                $scale->setMin((int)$data['scales'][$scale->getType()]['min']);
                $scale->setMax((int)$data['scales'][$scale->getType()]['max']);

                $scalesByType[$scale->getType()] = $scale;
                $scalesRangesByType[$scale->getType()] = [
                    'min' => $scale->getMin(),
                    'max' => $scale->getMax(),
                ];

                $this->scaleTable->save($scale, false);
            }
        }
        $this->importCacheHelper->addItemToArrayCache('scales_for_old_structure', $scalesRangesByType, 'current');

        if (!empty($data['scalesComments'])) {
            /** @var Entity\ScaleComment $scaleComment */
            foreach ($this->scaleCommentTable->findByAnr($anr) as $scaleComment) {
                if ($scaleComment->getScaleImpactType() === null || $scaleComment->getScaleImpactType()->isSys()) {
                    $this->scaleCommentTable->remove($scaleComment, false);
                }
            }
            $this->scaleCommentTable->flush();

            $scaleImpactTypeData = [];
            /** @var Entity\ScaleImpactType $scaleImpactType */
            foreach ($this->scaleImpactTypeTable->findByAnr($anr) as $index => $scaleImpactType) {
                $scaleImpactTypeData[$index + 1] = $scaleImpactType;
            }

            foreach ($data['scalesComments'] as $scalesCommentData) {
                /*
                 * Comments, which are not matched with a scale impact type, should not be created.
                 * This is possible only for exported files before v2.11.0.
                 */
                if (isset($scalesCommentData['scaleImpactType'])
                    && !isset($scalesCommentData['scaleImpactType']['labels'])
                    && !isset(
                        $scalesCommentData['scaleImpactType']['position'],
                        $scaleImpactTypeData[$scalesCommentData['scaleImpactType']['position']]
                    )
                ) {
                    continue;
                }

                $scale = $scalesByType[$scalesCommentData['scale']['type']];
                $scaleComment = (new Entity\ScaleComment())
                    ->setAnr($anr)
                    ->setScale($scale)
                    ->setScaleIndex($scalesCommentData['scaleIndex'] ?? $scalesCommentData['val'])
                    ->setScaleValue($scalesCommentData['scaleValue'] ?? $scalesCommentData['val'])
                    ->setComments([
                        'comment1' => $scalesCommentData['comment1'],
                        'comment2' => $scalesCommentData['comment2'],
                        'comment3' => $scalesCommentData['comment3'],
                        'comment4' => $scalesCommentData['comment4'],
                    ])
                    ->setCreator($this->connectedUser->getEmail());

                if (isset($scalesCommentData['scaleImpactType']['position'])) {
                    $scaleImpactTypePos = $scalesCommentData['scaleImpactType']['position'];
                    $scaleImpactType = $scaleImpactTypeData[$scaleImpactTypePos] ?? null;
                    $isSystem = $scaleImpactType !== null && $scaleImpactType->isSys();
                    /* Scale impact types are presented in the export separately since v2.11.0 */
                    if (isset($scalesCommentData['scaleImpactType']['labels'])
                        && !$isSystem
                        && ($scaleImpactType === null || $scaleImpactType->getLabel(
                                $anr->getLanguage()
                            ) !== $scalesCommentData['scaleImpactType']['labels']['label' . $anr->getLanguage()]
                        )
                    ) {
                        $scaleImpactType = (new Entity\ScaleImpactType())
                            ->setType($scalesCommentData['scaleImpactType']['type'])
                            ->setLabels($scalesCommentData['scaleImpactType']['labels'])
                            ->setIsSys($scalesCommentData['scaleImpactType']['isSys'])
                            ->setIsHidden($scalesCommentData['scaleImpactType']['isHidden'])
                            ->setAnr($anr)
                            ->setScale($scale)
                            ->setCreator($this->connectedUser->getEmail());

                        $this->scaleImpactTypeTable->save($scaleImpactType, false);

                        $scaleImpactTypesData[$scaleImpactTypePos] = $scaleImpactType;
                    }
                    if ($scaleImpactType === null) {
                        continue;
                    }

                    /* We may overwrite the comments if position is matched but scale type labels are different */
                    $scaleComment->setScaleImpactType($scaleImpactType);
                }

                $this->scaleCommentTable->save($scaleComment, false);
            }
            $this->scaleCommentTable->flush();
        }
    }
}
