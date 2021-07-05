<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Service;

use Doctrine\ORM\EntityNotFoundException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Monarc\Core\Exception\Exception;
use Monarc\Core\Model\Entity\AnrSuperClass;
use Monarc\Core\Model\Entity\InstanceSuperClass;
use Monarc\Core\Model\Entity\UserSuperClass;
use Monarc\Core\Service\ConfigService;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\Core\Service\TranslateService;
use Monarc\FrontOffice\Model\Entity\Instance;
use Monarc\FrontOffice\Model\Entity\InstanceRiskOp;
use Monarc\FrontOffice\Model\Entity\OperationalInstanceRiskScale;
use Monarc\FrontOffice\Model\Entity\OperationalRiskScale;
use Monarc\FrontOffice\Model\Entity\OperationalRiskScaleComment;
use Monarc\FrontOffice\Model\Entity\RolfRisk;
use Monarc\FrontOffice\Model\Table\AnrTable;
use Monarc\FrontOffice\Model\Table\InstanceRiskOpTable;
use Monarc\FrontOffice\Model\Table\InstanceTable;
use Monarc\FrontOffice\Model\Table\OperationalInstanceRiskScaleTable;
use Monarc\FrontOffice\Model\Table\OperationalRiskScaleTable;
use Monarc\FrontOffice\Model\Table\RolfRiskTable;
use Monarc\FrontOffice\Model\Table\TranslationTable;
use Monarc\FrontOffice\Service\Traits\RecommendationsPositionsUpdateTrait;

class AnrInstanceRiskOpService
{
    use RecommendationsPositionsUpdateTrait;

    private AnrTable $anrTable;

    private InstanceTable $instanceTable;

    private InstanceRiskOpTable $instanceRiskOpTable;

    private OperationalInstanceRiskScaleTable $operationalInstanceRiskScaleTable;

    private TranslationTable $translationTable;

    private RolfRiskTable $rolfRiskTable;

    private UserSuperClass $connectedUser;

    private OperationalRiskScaleTable $operationalRiskScaleTable;

    private ConfigService $configService;

    private TranslateService$translateService;

    public function __construct(
        AnrTable $anrTable,
        InstanceTable $instanceTable,
        InstanceRiskOpTable $instanceRiskOpTable,
        RolfRiskTable $rolfRiskTable,
        ConnectedUserService $connectedUserService,
        OperationalInstanceRiskScaleTable $operationalInstanceRiskScaleTable,
        OperationalRiskScaleTable $operationalRiskScaleTable,
        TranslationTable $translationTable,
        ConfigService $configService,
        TranslateService $translateService
    ) {
        $this->anrTable = $anrTable;
        $this->instanceTable = $instanceTable;
        $this->instanceRiskOpTable = $instanceRiskOpTable;
        $this->rolfRiskTable = $rolfRiskTable;
        $this->connectedUser = $connectedUserService->getConnectedUser();
        $this->operationalInstanceRiskScaleTable = $operationalInstanceRiskScaleTable;
        $this->operationalRiskScaleTable = $operationalRiskScaleTable;
        $this->translationTable = $translationTable;
        $this->configService = $configService;
        $this->translateService = $translateService;
    }

    public function createSpecificRiskOp(array $data): int
    {
        $instance = $this->instanceTable->findById((int)$data['instance']);
        $anr = $instance->getAnr();

        if ((int)$data['source'] === 2) {
            $rolfRisk = (new RolfRisk())
                ->setAnr($anr)
                ->setCode((string)$data['code'])
                ->setLabels(['label' . $anr->getLanguage() => $data['label']])
                ->setDescriptions(['description' . $anr->getLanguage() => $data['description'] ?? ''])
                ->setCreator($this->connectedUser->getFirstname() . ' ' . $this->connectedUser->getLastname());
            $this->rolfRiskTable->saveEntity($rolfRisk, true);
        } else {
            $rolfRisk = $this->rolfRiskTable->findById((int)$data['risk']);
            $operationalInstanceRisk = $this->instanceRiskOpTable->findByAnrInstanceAndRolfRisk(
                $anr,
                $instance,
                $rolfRisk
            );
            if ($operationalInstanceRisk !== null) {
                throw new Exception("This risk already exists in this instance", 412);
            }
        }

        $operationalInstanceRisk = (new InstanceRiskOp())
            ->setAnr($anr)
            ->setRolfRisk($rolfRisk)
            ->setInstance($instance)
            ->setObject($instance->getObject())
            ->setSpecific(1)
            ->setRiskCacheCode($rolfRisk->getCode())
            ->setRiskCacheLabels([
                'riskCacheLabel1' => $rolfRisk->getLabel(1),
                'riskCacheLabel2' => $rolfRisk->getLabel(2),
                'riskCacheLabel3' => $rolfRisk->getLabel(3),
                'riskCacheLabel4' => $rolfRisk->getLabel(4),
            ])
            ->setRiskCacheDescriptions([
                'riskCacheDescription1' => $rolfRisk->getDescription(1),
                'riskCacheDescription2' => $rolfRisk->getDescription(2),
                'riskCacheDescription3' => $rolfRisk->getDescription(3),
                'riskCacheDescription4' => $rolfRisk->getDescription(4),
            ])
            ->setCreator($this->connectedUser->getFirstname() . ' ' . $this->connectedUser->getLastname());

        $this->instanceRiskOpTable->saveEntity($operationalInstanceRisk, false);

        $operationalRiskScales = $this->operationalRiskScaleTable->findByAnr($instance->getAnr());
        foreach ($operationalRiskScales as $operationalRiskScale) {
            $operationalInstanceRiskScale = (new OperationalInstanceRiskScale())
                ->setAnr($anr)
                ->setOperationalInstanceRisk($operationalInstanceRisk)
                ->setOperationalRiskScale($operationalRiskScale)
                ->setCreator($this->connectedUser->getEmail());
            $this->operationalInstanceRiskScaleTable->save($operationalInstanceRiskScale, false);
        }

        $this->instanceRiskOpTable->getDb()->flush();

        return $operationalInstanceRisk->getId();
    }

    /**
     * @throws EntityNotFoundException
     * @throws Exception
     */
    public function update(int $id, array $data)
    {
        // TODO: implement Permissions validator and inject it here. similar to \Monarc\Core\Service\AbstractService::deleteFromAnr

        /** @var InstanceRiskOp $operationalInstanceRisk */
        $operationalInstanceRisk = $this->instanceRiskOpTable->findById($id);
        if (!empty($data['kindOfMeasure'])) {
            $operationalInstanceRisk->setKindOfMeasure((int)$data['kindOfMeasure']);
        }
        if (!empty($data['comment'])) {
            $operationalInstanceRisk->setComment($data['comment']);
        }
        if (!empty($data['netProb']) && $operationalInstanceRisk->getNetProb() !== $data['netProb']) {
            $this->verifyScaleProbabilityValue($operationalInstanceRisk->getAnr(), (int)$data['netProb']);
            $operationalInstanceRisk->setNetProb((int)$data['netProb']);
        }
        if (!empty($data['brutProb']) && $operationalInstanceRisk->getBrutProb() !== $data['brutProb']) {
            $this->verifyScaleProbabilityValue($operationalInstanceRisk->getAnr(), (int)$data['brutProb']);
            $operationalInstanceRisk->setBrutProb((int)$data['brutProb']);
        }

        $operationalInstanceRisk->setUpdater(
            $this->connectedUser->getFirstname() . ' ' . $this->connectedUser->getLastname()
        );

        $this->instanceRiskOpTable->saveEntity($operationalInstanceRisk);

        $this->updateInstanceRiskRecommendationsPositions($operationalInstanceRisk);

        return $operationalInstanceRisk->getJsonArray();
    }

    /**
     * @throws EntityNotFoundException
     * @throws Exception
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function updateScaleValue($id, $data): void
    {
        /** @var OperationalInstanceRiskScale $operationInstanceRiskScale */
        $operationInstanceRiskScale = $this->operationalInstanceRiskScaleTable->findById($data['instanceRiskScaleId']);
        if ($operationInstanceRiskScale === null) {
            throw EntityNotFoundException::fromClassNameAndIdentifier(
                \get_class($this->operationalInstanceRiskScaleTable),
                $data['instanceRiskScaleId']
            );
        }

        if (!empty($data['netValue']) && $operationInstanceRiskScale->getNetValue() !== (int)$data['netValue']) {
            $this->verifyScaleValue($operationInstanceRiskScale, (int)$data['netValue']);
            $operationInstanceRiskScale->setNetValue((int)$data['netValue']);
        }
        if (!empty($data['brutValue']) && $operationInstanceRiskScale->getBrutValue() !== (int)$data['brutValue']) {
            $this->verifyScaleValue($operationInstanceRiskScale, (int)$data['brutValue']);
            $operationInstanceRiskScale->setBrutValue((int)$data['brutValue']);
        }

        $operationInstanceRiskScale->setUpdater($this->connectedUser->getEmail());

        /** @var InstanceRiskOp $operationalInstanceRisk */
        $operationalInstanceRisk = $this->instanceRiskOpTable->findById($id);

        $this->updateRiskCacheValues($operationalInstanceRisk);

        $this->operationalInstanceRiskScaleTable->save($operationInstanceRiskScale);
    }

    public function getOperationalRisks(int $anrId, int $instanceId = null, array $params = [])
    {
        $instancesIds = $this->determineInstancesIdsFromParam($instanceId);

        $anr = $this->anrTable->findById($anrId);
        $anrLanguage = $anr->getLanguage();

        $operationalInstanceRisks = $this->instanceRiskOpTable->findByAnrInstancesAndFilterParams(
            $anr,
            $instancesIds,
            $params
        );

        $result = [];
        $operationalRisksScalesTranslations = $this->translationTable->findByAnrTypesAndLanguageIndexedByKey(
            $anr,
            [OperationalRiskScale::class, OperationalRiskScaleComment::class],
            strtolower($this->configService->getLanguageCodes()[$anrLanguage])
        );
        foreach ($operationalInstanceRisks as $operationalInstanceRisk) {
            $recommendationUuids = [];
            foreach ($operationalInstanceRisk->getRecommendationRisks() as $recommendationRisk) {
                $recommendationUuids[] = $recommendationRisk->getRecommandation()->getUuid();
            }

            $scalesData = [];
            foreach ($operationalInstanceRisk->getOperationalInstanceRiskScales() as $operationalInstanceRiskScale) {
                $operationalRiskScale = $operationalInstanceRiskScale->getOperationalRiskScale();
                $scalesData[$operationalRiskScale->getId()] = [
                    'operationalInstanceRiskId' => $operationalInstanceRisk->getId(),
                    'instanceRiskScaleId' => $operationalInstanceRiskScale->getId(),
                    'label' => $operationalRisksScalesTranslations[$operationalRiskScale->getLabelTranslationKey()]
                        ->getValue(),
                    'netValue' => $operationalInstanceRiskScale->getNetValue(),
                    'brutValue' => $operationalInstanceRiskScale->getBrutValue(),
                    'targetValue' => $operationalInstanceRiskScale->getTargetedValue(),
                ];
            }

            $result[] = [
                'id' => $operationalInstanceRisk->getId(),
                'rolfRisk' => $operationalInstanceRisk->getRolfRisk()->getId(),
                'label1' => $operationalInstanceRisk->getRiskCacheLabel(1),
                'label2' => $operationalInstanceRisk->getRiskCacheLabel(2),
                'label3' => $operationalInstanceRisk->getRiskCacheLabel(3),
                'label4' => $operationalInstanceRisk->getRiskCacheLabel(4),
                'description1' => $operationalInstanceRisk->getRiskCacheDescription(1),
                'description2' => $operationalInstanceRisk->getRiskCacheDescription(2),
                'description3' => $operationalInstanceRisk->getRiskCacheDescription(3),
                'description4' => $operationalInstanceRisk->getRiskCacheDescription(4),
                'netProb' => $operationalInstanceRisk->getNetProb(),
                'brutProb' => $operationalInstanceRisk->getBrutProb(),
                'targetProb' => $operationalInstanceRisk->getTargetedProb(),
                'scales' => $scalesData,
                'cacheNetRisk' => $operationalInstanceRisk->getCacheNetRisk(),
                'cacheBrutRisk' => $operationalInstanceRisk->getCacheBrutRisk(),
                'cacheTargetRisk' => $operationalInstanceRisk->getCacheTargetedRisk(),
                'kindOfMeasure' => $operationalInstanceRisk->getKindOfMeasure(),
                'comment' => $operationalInstanceRisk->getComment(),
                'specific' => $operationalInstanceRisk->getSpecific(),
                't' => $operationalInstanceRisk->getKindOfMeasure() === InstanceRiskOp::KIND_NOT_TREATED ? 0 : 1,
                'position' => $operationalInstanceRisk->getInstance()->getPosition(),
                'instanceInfos' => [
                    'id' => $operationalInstanceRisk->getInstance()->getId(),
                    'scope' => $operationalInstanceRisk->getInstance()->getObject()->getScope(),
                    'name' . $anrLanguage => $operationalInstanceRisk->getInstance()->{'getName' . $anrLanguage}(),
                ],
                'recommendations' => implode(',', $recommendationUuids),
            ];
        }

        return $result;
    }

    public function getOperationalRisksInCsv(int $anrId, int $instanceId = null, array $params = []): string
    {
        $instancesIds = $this->determineInstancesIdsFromParam($instanceId);
        $anr = $this->anrTable->findById($anrId);
        $anrLanguage = $anr->getLanguage();

        $operationalRiskScales = $this->operationalRiskScaleTable->findByAnrAndType(
            $anr,
            OperationalRiskScale::TYPE_IMPACT
        );
        $operationalRisksScalesTranslations = $this->translationTable->findByAnrTypesAndLanguageIndexedByKey(
            $anr,
            [OperationalRiskScale::class, OperationalRiskScaleComment::class],
            strtolower($this->configService->getLanguageCodes()[$anrLanguage])
        );

        $tableHeaders = [
            'instanceData' => $this->translateService->translate('Asset', $anrLanguage),
            'label' => $this->translateService->translate('Risk description', $anrLanguage),
        ];

        if ($anr->getShowRolfBrut() === 1) {
            $translatedRiskValueDescription = $this->translateService->translate('Inherent risk', $anrLanguage);
            $tableHeaders['brutProb'] = $this->translateService->translate('Prob.', $anrLanguage)
                . "(" . $translatedRiskValueDescription . ")";
            foreach ($operationalRiskScales as $operationalRiskScale) {
                $label = $operationalRisksScalesTranslations[$operationalRiskScale->getLabelTranslationKey()]
                    ->getValue();
                $tableHeaders[$label] = $label . " (" . $translatedRiskValueDescription . ")";
            }
            $tableHeaders['cacheBrutRisk'] = $translatedRiskValueDescription;
        }

        $translatedNetRiskDescription = $this->translateService->translate('Net risk', $anrLanguage);
        $tableHeaders['netProb'] = $this->translateService->translate('Prob.', $anrLanguage) . "("
            . $translatedNetRiskDescription . ")";
        foreach ($operationalRiskScales as $operationalRiskScale) {
            $label = $operationalRisksScalesTranslations[$operationalRiskScale->getLabelTranslationKey()]
                ->getValue();
            $tableHeaders[$label] = $label . " (" . $translatedNetRiskDescription . ")";
        }
        $tableHeaders['cacheNetRisk'] = $translatedNetRiskDescription;
        $tableHeaders['comment'] = $this->translateService->translate('Existing controls', $anrLanguage);
        $tableHeaders['kindOfMeasure'] = $this->translateService->translate('Treatment', $anrLanguage);
        $tableHeaders['cacheTargetedRisk'] = $this->translateService->translate('Residual risk', $anrLanguage);

        $output = implode(',', array_values($tableHeaders)) . "\n";

        /* CSV export is done for all the risks. */
        unset($params['limit']);

        $operationalInstanceRisks = $this->instanceRiskOpTable->findByAnrInstancesAndFilterParams(
            $anr,
            $instancesIds,
            $params
        );
        foreach ($operationalInstanceRisks as $operationalInstanceRisk) {
            $values = [
                $operationalInstanceRisk->getInstance()->{'getName' . $anrLanguage}(),
                $operationalInstanceRisk->getRiskCacheLabel($anrLanguage),
            ];
            if ($anr->getShowRolfBrut() === 1) {
                $values[] = $operationalInstanceRisk->getBrutProb();
                foreach ($operationalInstanceRisk->getOperationalInstanceRiskScales() as $instanceRiskScale) {
                    $values[] = $instanceRiskScale->getBrutValue();
                }
                $values[] = $operationalInstanceRisk->getCacheBrutRisk();
            }
            $values[] = $operationalInstanceRisk->getNetProb();
            foreach ($operationalInstanceRisk->getOperationalInstanceRiskScales() as $instanceRiskScale) {
                $values[] = $instanceRiskScale->getNetValue();
            }
            $values[] = $operationalInstanceRisk->getCacheNetRisk();
            $values[] = $operationalInstanceRisk->getComment();
            $values[] = InstanceRiskOp::getAvailableMeasureTypes()[$operationalInstanceRisk->getKindOfMeasure()];
            $values[] = $operationalInstanceRisk->getCacheTargetedRisk();

            $output .= '"';
            $search = ['"', "\n"];
            $replace = ["'", ' '];
            $output .= implode('","', str_replace($search, $replace, $values));
            $output .= "\"\r\n";
        }

        return $output;
    }

    public function deleteFromAnr(int $id, int $anrId): void
    {
        $operationalInstanceRisk = $this->instanceRiskOpTable->findById($id);
        if (!$operationalInstanceRisk->isSpecific()) {
            throw new Exception('Only specific risks can be deleted.', 412);
        }

        // TODO: implement Permissions validator and inject it here. similar to \Monarc\Core\Service\AbstractService::deleteFromAnr

        $this->instanceRiskOpTable->deleteEntity($operationalInstanceRisk);

        $this->processRemovedInstanceRiskRecommendationsPositions($operationalInstanceRisk);
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function deleteOperationalRisks(InstanceSuperClass $instance): void
    {
        $operationalRisks = $this->instanceRiskOpTable->findByInstance($instance);
        foreach ($operationalRisks as $operationalRisk) {
            $this->instanceRiskOpTable->deleteEntity($operationalRisk, false);
        }
        $this->instanceRiskOpTable->getDb()->flush();
    }

    /**
     * @param Instance[] $instances
     *
     * @return array
     */
    private function extractInstancesAndTheirChildrenIds(array $instances): array
    {
        $instancesIds = [];
        $childInstancesIds = [];
        foreach ($instances as $instanceId => $instance) {
            $instancesIds[] = $instanceId;
            $childInstancesIds = $this->extractInstancesAndTheirChildrenIds($instance->getParameterValues('children'));
        }

        return array_merge($instancesIds, $childInstancesIds);
    }

    private function updateRiskCacheValues(InstanceRiskOp $operationalInstanceRisk, bool $flushChanges = false): void
    {
        foreach (['Brut', 'Net', 'Targeted'] as $valueType) {
            $max = -1;
            $probVal = $operationalInstanceRisk->{'get' . $valueType . 'Prob'}();
            if ($probVal !== -1) {
                foreach ($operationalInstanceRisk->getOperationalInstanceRiskScales() as $riskScale) {
                    $scaleValue = $riskScale->{'get' . $valueType . 'Value'}();
                    if ($scaleValue > -1 && ($probVal * $scaleValue) > $max) {
                        $max = $probVal * $scaleValue;
                    }
                }
            }

            if ($operationalInstanceRisk->{'getCache' . $valueType . 'Risk'}() !== $max) {
                $operationalInstanceRisk
                    ->setUpdater($this->connectedUser->getFirstname() . ' ' . $this->connectedUser->getLastname())
                    ->{'setCache' . $valueType . 'Risk'}($max);
                $this->instanceRiskOpTable->saveEntity($operationalInstanceRisk, false);
            }
        }

        if ($flushChanges === true) {
            $this->instanceRiskOpTable->getDb()->flush();
        }
    }

    private function verifyScaleValue(
        OperationalInstanceRiskScale $operationalInstanceRiskScale,
        int $scaleValue
    ): void {
        $operationalRiskScale = $operationalInstanceRiskScale->getOperationalRiskScale();
        $allowedValues = [];
        foreach ($operationalRiskScale->getOperationalRiskScaleComments() as $operationalRiskScaleComment) {
            $allowedValues[] = $operationalRiskScaleComment->getScaleValue();
        }

        if ($scaleValue !== -1 && !\in_array($scaleValue, $allowedValues, true)) {
            throw new Exception(sprintf(
                'The value %d should be between one of [%s]',
                $scaleValue,
                implode(', ', $allowedValues)
            ), 412);
        }
    }

    private function verifyScaleProbabilityValue(AnrSuperClass $anr, int $scaleProbabilityValue): void
    {
        $operationalRiskScales = $this->operationalRiskScaleTable->findByAnrAndType(
            $anr,
            OperationalRiskScale::TYPE_LIKELIHOOD
        );
        /* There is only one scale of the TYPE_LIKELIHOOD. */
        $operationalRiskScale = $operationalRiskScales->current();
        if ($scaleProbabilityValue !== -1
            && ($scaleProbabilityValue < $operationalRiskScale->getMin()
                || $scaleProbabilityValue > $operationalRiskScale->getMax()
            )
        ) {
            throw new Exception(sprintf(
                'The value %d should be between %d and %d.',
                $scaleProbabilityValue,
                $operationalRiskScale->getMin(),
                $operationalRiskScale->getMax()
            ), 412);
        }
    }

    private function determineInstancesIdsFromParam($instanceId): array
    {
        $instancesIds = [];
        if ($instanceId !== null) {
            $instance = $this->instanceTable->findById($instanceId);
            $this->instanceTable->initTree($instance);
            $instancesIds = $this->extractInstancesAndTheirChildrenIds([$instance->getId() => $instance]);
        }

        return $instancesIds;
    }
}
