<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Service;

use Doctrine\ORM\EntityNotFoundException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Monarc\Core\Exception\Exception;
use Monarc\Core\Model\Entity\AnrSuperClass;
use Monarc\Core\Model\Entity\InstanceRiskOpSuperClass;
use Monarc\Core\Model\Entity\InstanceSuperClass;
use Monarc\Core\Model\Entity\ObjectSuperClass;
use Monarc\Core\Model\Entity\OperationalInstanceRiskScaleSuperClass;
use Monarc\Core\Model\Entity\OperationalRiskScaleTypeSuperClass;
use Monarc\Core\Model\Entity\RolfRiskSuperClass;
use Monarc\Core\Model\Table\InstanceRiskOpTable as CoreInstanceRiskOpTable;
use Monarc\Core\Service\ConfigService;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\Core\Service\InstanceRiskOpService;
use Monarc\Core\Service\TranslateService;
use Monarc\FrontOffice\Model\Entity\Instance;
use Monarc\FrontOffice\Model\Entity\InstanceRiskOp;
use Monarc\FrontOffice\Model\Entity\OperationalInstanceRiskScale;
use Monarc\FrontOffice\Model\Entity\OperationalRiskScale;
use Monarc\FrontOffice\Model\Entity\OperationalRiskScaleComment;
use Monarc\FrontOffice\Model\Entity\OperationalRiskScaleType;
use Monarc\FrontOffice\Model\Entity\RolfRisk;
use Monarc\FrontOffice\Model\Entity\InstanceRiskOwner;
use Monarc\FrontOffice\Model\Table\AnrTable;
use Monarc\FrontOffice\Model\Table\InstanceRiskOpTable;
use Monarc\FrontOffice\Model\Table\InstanceRiskOwnerTable;
use Monarc\FrontOffice\Model\Table\InstanceTable;
use Monarc\FrontOffice\Model\Table\OperationalInstanceRiskScaleTable;
use Monarc\FrontOffice\Model\Table\OperationalRiskScaleTable;
use Monarc\FrontOffice\Model\Table\OperationalRiskScaleTypeTable;
use Monarc\FrontOffice\Model\Table\RolfRiskTable;
use Monarc\FrontOffice\Model\Table\RolfTagTable;
use Monarc\FrontOffice\Model\Table\TranslationTable;
use Monarc\FrontOffice\Service\Traits\RecommendationsPositionsUpdateTrait;

class AnrInstanceRiskOpService extends InstanceRiskOpService
{
    use RecommendationsPositionsUpdateTrait;

    /** @var InstanceRiskOpTable $instanceRiskOpTable */
    protected CoreInstanceRiskOpTable $instanceRiskOpTable;

    protected RolfRiskTable $rolfRiskTable;

    protected InstanceRiskOwnerTable $instanceRiskOwnerTable;

    private array $operationalRiskScales = [];

    private OperationalRiskScaleTable $operationalRiskScaleTable;

    public function __construct(
        AnrTable $anrTable,
        InstanceTable $instanceTable,
        InstanceRiskOpTable $instanceRiskOpTable,
        OperationalInstanceRiskScaleTable $operationalInstanceRiskScaleTable,
        RolfRiskTable $rolfRiskTable,
        RolfTagTable $rolfTagTable,
        ConnectedUserService $connectedUserService,
        OperationalRiskScaleTable $operationalRiskScaleTable,
        OperationalRiskScaleTypeTable $operationalRiskScaleTypeTable,
        TranslationTable $translationTable,
        ConfigService $configService,
        TranslateService $translateService,
        InstanceRiskOwnerTable $instanceRiskOwnerTable
    ) {
        parent::__construct(
            $anrTable,
            $instanceTable,
            $instanceRiskOpTable,
            $operationalInstanceRiskScaleTable,
            $rolfTagTable,
            $connectedUserService,
            $translationTable,
            $translateService,
            $operationalRiskScaleTypeTable,
            $configService
        );
        $this->rolfRiskTable = $rolfRiskTable;
        $this->instanceRiskOwnerTable = $instanceRiskOwnerTable;
        $this->operationalRiskScaleTable = $operationalRiskScaleTable;
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

        $operationalRiskScaleTypes = $this->operationalRiskScaleTypeTable->findByAnrAndScaleType(
            $instance->getAnr(),
            OperationalRiskScale::TYPE_IMPACT
        );
        foreach ($operationalRiskScaleTypes as $operationalRiskScaleType) {
            $operationalInstanceRiskScale = (new OperationalInstanceRiskScale())
                ->setAnr($anr)
                ->setOperationalInstanceRisk($operationalInstanceRisk)
                ->setOperationalRiskScaleType($operationalRiskScaleType)
                ->setCreator($this->connectedUser->getEmail());
            $this->operationalInstanceRiskScaleTable->save($operationalInstanceRiskScale, false);
        }

        $this->instanceRiskOpTable->getDb()->flush();

        return $operationalInstanceRisk->getId();
    }

    public function getOperationalRisks(int $anrId, int $instanceId = null, array $params = []): array
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
        $scaleTypesTranslations = $this->translationTable->findByAnrTypesAndLanguageIndexedByKey(
            $anr,
            [OperationalRiskScaleType::TRANSLATION_TYPE_NAME, OperationalRiskScaleComment::TRANSLATION_TYPE_NAME],
            $this->getAnrLanguageCode($anr)
        );
        foreach ($operationalInstanceRisks as $operationalInstanceRisk) {
            $recommendationUuids = [];
            foreach ($operationalInstanceRisk->getRecommendationRisks() as $recommendationRisk) {
                $recommendationUuids[] = $recommendationRisk->getRecommandation()->getUuid();
            }

            $scalesData = [];
            foreach ($operationalInstanceRisk->getOperationalInstanceRiskScales() as $operationalInstanceRiskScale) {
                $operationalRiskScaleType = $operationalInstanceRiskScale->getOperationalRiskScaleType();
                $scalesData[$operationalRiskScaleType->getId()] = [
                    'instanceRiskScaleId' => $operationalInstanceRiskScale->getId(),
                    'label' => $scaleTypesTranslations[$operationalRiskScaleType->getLabelTranslationKey()]->getValue(),
                    'netValue' => $operationalInstanceRiskScale->getNetValue(),
                    'brutValue' => $operationalInstanceRiskScale->getBrutValue(),
                    'targetValue' => $operationalInstanceRiskScale->getTargetedValue(),
                    'isHidden' => $operationalRiskScaleType->isHidden(),
                ];
            }

            $result[] = [
                'id' => $operationalInstanceRisk->getId(),
                'rolfRisk' => $operationalInstanceRisk->getRolfRisk()->getId(),
                'label' . $anrLanguage => $operationalInstanceRisk->getRiskCacheLabel($anrLanguage),
                'description' . $anrLanguage => $operationalInstanceRisk->getRiskCacheDescription($anrLanguage),
                'context' => $operationalInstanceRisk->getContext(),
                'owner' => $operationalInstanceRisk->getOwner() ? $operationalInstanceRisk->getOwner()->getName() : '',
                'netProb' => $operationalInstanceRisk->getNetProb(),
                'brutProb' => $operationalInstanceRisk->getBrutProb(),
                'targetedProb' => $operationalInstanceRisk->getTargetedProb(),
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

    /**
     * @throws EntityNotFoundException
     * @throws Exception
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function update(int $id, array $data): array
    {
        // TODO: implement Permissions validator and inject it here. similar to \Monarc\Core\Service\AbstractService::deleteFromAnr

        /** @var InstanceRiskOp $operationalInstanceRisk */
        $operationalInstanceRisk = $this->instanceRiskOpTable->findById($id);
        if (isset($data['kindOfMeasure'])) {
            $operationalInstanceRisk->setKindOfMeasure((int)$data['kindOfMeasure']);
        }
        if (isset($data['comment'])) {
            $operationalInstanceRisk->setComment($data['comment']);
        }
        if (isset($data['netProb']) && $operationalInstanceRisk->getNetProb() !== $data['netProb']) {
            $this->verifyScaleProbabilityValue($operationalInstanceRisk->getAnr(), (int)$data['netProb']);
            $operationalInstanceRisk->setNetProb((int)$data['netProb']);
        }
        if (isset($data['brutProb']) && $operationalInstanceRisk->getBrutProb() !== $data['brutProb']) {
            $this->verifyScaleProbabilityValue($operationalInstanceRisk->getAnr(), (int)$data['brutProb']);
            $operationalInstanceRisk->setBrutProb((int)$data['brutProb']);
        }
        if (isset($data['targetedProb']) && $operationalInstanceRisk->getTargetedProb() !== $data['targetedProb']) {
            $this->verifyScaleProbabilityValue($operationalInstanceRisk->getAnr(), (int)$data['targetedProb']);
            $operationalInstanceRisk->setTargetedProb((int)$data['targetedProb']);
        }
        if (isset($data['owner'])) {
            $this->processRiskOwnerName((string)$data['owner'], $operationalInstanceRisk);
        }
        if (isset($data['context'])) {
            $operationalInstanceRisk->setContext($data['context']);
        }

        $operationalInstanceRisk->setUpdater(
            $this->connectedUser->getFirstname() . ' ' . $this->connectedUser->getLastname()
        );

        $this->updateRiskCacheValues($operationalInstanceRisk);

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
    public function updateScaleValue($id, $data): array
    {
        /** @var OperationalInstanceRiskScale $operationInstanceRiskScale */
        $operationInstanceRiskScale = $this->operationalInstanceRiskScaleTable->findById($data['instanceRiskScaleId']);
        if ($operationInstanceRiskScale === null) {
            throw EntityNotFoundException::fromClassNameAndIdentifier(
                \get_class($this->operationalInstanceRiskScaleTable),
                $data['instanceRiskScaleId']
            );
        }

        if (isset($data['netValue']) && $operationInstanceRiskScale->getNetValue() !== (int)$data['netValue']) {
            $this->verifyScaleValue($operationInstanceRiskScale, (int)$data['netValue']);
            $operationInstanceRiskScale->setNetValue((int)$data['netValue']);
        }
        if (isset($data['brutValue']) && $operationInstanceRiskScale->getBrutValue() !== (int)$data['brutValue']) {
            $this->verifyScaleValue($operationInstanceRiskScale, (int)$data['brutValue']);
            $operationInstanceRiskScale->setBrutValue((int)$data['brutValue']);
        }
        if (isset($data['targetValue'])
            && $operationInstanceRiskScale->getTargetedValue() !== (int)$data['targetValue']
        ) {
            $this->verifyScaleValue($operationInstanceRiskScale, (int)$data['targetValue']);
            $operationInstanceRiskScale->setTargetedValue((int)$data['targetValue']);
        }

        $operationInstanceRiskScale->setUpdater($this->connectedUser->getEmail());

        /** @var InstanceRiskOp $operationalInstanceRisk */
        $operationalInstanceRisk = $this->instanceRiskOpTable->findById($id);

        $this->updateRiskCacheValues($operationalInstanceRisk);

        $this->operationalInstanceRiskScaleTable->save($operationInstanceRiskScale);

        return $operationalInstanceRisk->getJsonArray();
    }

    public function getOperationalRisksInCsv(int $anrId, int $instanceId = null, array $params = []): string
    {
        $instancesIds = $this->determineInstancesIdsFromParam($instanceId);
        $anr = $this->anrTable->findById($anrId);
        $anrLanguage = $anr->getLanguage();

        $operationalRiskScaleTypes = $this->operationalRiskScaleTypeTable->findByAnrAndScaleType(
            $anr,
            OperationalRiskScale::TYPE_IMPACT
        );
        $scaleTypesTranslations = $this->translationTable->findByAnrTypesAndLanguageIndexedByKey(
            $anr,
            [OperationalRiskScaleType::TRANSLATION_TYPE_NAME],
            $this->getAnrLanguageCode($anr)
        );

        $tableHeaders = [
            'instanceData' => $this->translateService->translate('Asset', $anrLanguage),
            'label' => $this->translateService->translate('Risk description', $anrLanguage),
        ];

        if ($anr->getShowRolfBrut() === 1) {
            $translatedRiskValueDescription = $this->translateService->translate('Inherent risk', $anrLanguage);
            $tableHeaders['brutProb'] = $this->translateService->translate('Prob.', $anrLanguage)
                . "(" . $translatedRiskValueDescription . ")";
            foreach ($operationalRiskScaleTypes as $operationalRiskScaleType) {
                $label = $scaleTypesTranslations[$operationalRiskScaleType->getLabelTranslationKey()]
                    ->getValue();
                $tableHeaders[$label] = $label . " (" . $translatedRiskValueDescription . ")";
            }
            $tableHeaders['cacheBrutRisk'] = $translatedRiskValueDescription;
        }

        $translatedNetRiskDescription = $this->translateService->translate('Net risk', $anrLanguage);
        $tableHeaders['netProb'] = $this->translateService->translate('Prob.', $anrLanguage) . "("
            . $translatedNetRiskDescription . ")";
        foreach ($operationalRiskScaleTypes as $operationalRiskScaleType) {
            $label = $scaleTypesTranslations[$operationalRiskScaleType->getLabelTranslationKey()]
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

    /**
     * @throws EntityNotFoundException
     * @throws Exception
     * @throws ORMException
     * @throws OptimisticLockException
     */
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

    public function updateRiskCacheValues(InstanceRiskOp $operationalInstanceRisk, bool $flushChanges = false): void
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

    /**
     * Called from InstanceRiskOpService::createInstanceRisksOp
     */
    protected function createInstanceRiskOpObjectFromInstanceObjectAndRolfRisk(
        InstanceSuperClass $instance,
        ObjectSuperClass $object,
        RolfRiskSuperClass $rolfRisk
    ): InstanceRiskOpSuperClass {
        return (new InstanceRiskOp())
            ->setAnr($instance->getAnr())
            ->setInstance($instance)
            ->setObject($object)
            ->setRolfRisk($rolfRisk)
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
            ]);
    }

    /**
     * Called from InstanceRiskOpService::createInstanceRisksOp
     */
    protected function createOperationalInstanceRiskScaleObject(
        InstanceRiskOpSuperClass $instanceRiskOp,
        OperationalRiskScaleTypeSuperClass $operationalRiskScaleType
    ): OperationalInstanceRiskScaleSuperClass {
        return (new OperationalInstanceRiskScale())
            ->setAnr($instanceRiskOp->getAnr())
            ->setOperationalInstanceRisk($instanceRiskOp)
            ->setOperationalRiskScaleType($operationalRiskScaleType)
            ->setCreator($this->connectedUser->getEmail());
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

    private function verifyScaleValue(
        OperationalInstanceRiskScale $operationalInstanceRiskScale,
        int $scaleValue
    ): void {
        $operationalRiskScaleType = $operationalInstanceRiskScale->getOperationalRiskScaleType();
        $allowedValues = [];
        foreach ($operationalRiskScaleType->getOperationalRiskScaleComments() as $operationalRiskScaleComment) {
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
        if (!isset($this->operationalRiskScales[OperationalRiskScale::TYPE_LIKELIHOOD])) {
            $this->operationalRiskScales[OperationalRiskScale::TYPE_LIKELIHOOD] = $this->operationalRiskScaleTable
                ->findByAnrAndType($anr, OperationalRiskScale::TYPE_LIKELIHOOD);
        }
        /* There is only one scale of the TYPE_LIKELIHOOD. */
        $operationalRiskScale = current($this->operationalRiskScales[OperationalRiskScale::TYPE_LIKELIHOOD]);
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

    /**
     * @param string $riskOwnerName
     * @param InstanceRiskOp $operationalInstanceRisk
     *
     * @return string
     * @throws ORMException
     * @throws OptimisticLockException
     */
    private function processRiskOwnerName(string $riskOwnerName, InstanceRiskOp $operationalInstanceRisk): void
    {
        if (empty($riskOwnerName)) {
            $operationalInstanceRisk->setOwner(null);
        } else {
            $instanceRiskOwner = $this->instanceRiskOwnerTable->findByAnrAndName(
                $operationalInstanceRisk->getAnr(),
                $riskOwnerName
            );
            if ($instanceRiskOwner === null) {
                $instanceRiskOwner = (new InstanceRiskOwner())
                    ->setAnr($operationalInstanceRisk->getAnr())
                    ->setName($riskOwnerName)
                    ->setCreator($this->connectedUser->getEmail());

                $this->instanceRiskOwnerTable->save($instanceRiskOwner, false);

                $operationalInstanceRisk->setOwner($instanceRiskOwner);
            } elseif ($operationalInstanceRisk->getOwner() !== $instanceRiskOwner) {
                $operationalInstanceRisk->setOwner($instanceRiskOwner);
            }
        }
    }
}
