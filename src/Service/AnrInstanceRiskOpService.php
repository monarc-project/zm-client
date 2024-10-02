<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Exception\Exception;
use Monarc\Core\Entity as CoreEntity;
use Monarc\Core\Service as CoreService;
use Monarc\Core\Service\Traits\OperationalRiskScaleVerificationTrait;
use Monarc\FrontOffice\Entity;
use Monarc\FrontOffice\Service\Traits\RecommendationsPositionsUpdateTrait;
use Monarc\FrontOffice\Table;

class AnrInstanceRiskOpService
{
    use OperationalRiskScaleVerificationTrait;
    use RecommendationsPositionsUpdateTrait;

    public const CREATION_SOURCE_FROM_RISK = 1;
    public const CREATION_SOURCE_NEW_RISK = 2;

    private CoreEntity\UserSuperClass $connectedUser;

    private array $operationalRiskImpactScales = [];

    public function __construct(
        private Table\InstanceRiskOpTable $instanceRiskOpTable,
        private Table\InstanceTable $instanceTable,
        private Table\OperationalInstanceRiskScaleTable $operationalInstanceRiskScaleTable,
        private Table\RolfRiskTable $rolfRiskTable,
        private Table\RolfTagTable $rolfTagTable,
        private Table\OperationalRiskScaleTable $operationalRiskScaleTable,
        private Table\OperationalRiskScaleTypeTable $operationalRiskScaleTypeTable,
        private Table\RecommendationTable $recommendationTable,
        private Table\RecommendationRiskTable $recommendationRiskTable,
        private CoreService\ConfigService $configService,
        private CoreService\TranslateService $translateService,
        private CoreService\Helper\ScalesCacheHelper $scalesCacheHelper,
        private InstanceRiskOwnerService $instanceRiskOwnerService,
        CoreService\ConnectedUserService $connectedUserService
    ) {
        $this->connectedUser = $connectedUserService->getConnectedUser();
    }

    public function getOperationalRisks(Entity\Anr $anr, int $instanceId = null, array $params = []): array
    {
        $instancesIds = [];
        if ($instanceId !== null) {
            /** @var Entity\Instance $instance */
            $instance = $this->instanceTable->findByIdAndAnr($instanceId, $anr);
            $instancesIds = $instance->getSelfAndChildrenIds();
        }

        $operationalInstanceRisks = $this->instanceRiskOpTable->findByAnrInstancesAndFilterParams(
            $anr,
            $instancesIds,
            $params
        );

        $anrLanguage = $anr->getLanguage();
        $result = [];
        foreach ($operationalInstanceRisks as $operationalInstanceRisk) {
            $recommendationUuids = [];
            foreach ($operationalInstanceRisk->getRecommendationRisks() as $recommendationRisk) {
                if ($recommendationRisk->getRecommendation() !== null) {
                    $recommendationUuids[] = $recommendationRisk->getRecommendation()->getUuid();
                }
            }

            $scalesData = [];
            foreach ($operationalInstanceRisk->getOperationalInstanceRiskScales() as $operationalInstanceRiskScale) {
                $operationalRiskScaleType = $operationalInstanceRiskScale->getOperationalRiskScaleType();
                $scalesData[$operationalRiskScaleType->getId()] = [
                    'instanceRiskScaleId' => $operationalInstanceRiskScale->getId(),
                    'label' => $operationalRiskScaleType->getLabel(),
                    'netValue' => $operationalInstanceRiskScale->getNetValue(),
                    'brutValue' => $operationalInstanceRiskScale->getBrutValue(),
                    'targetedValue' => $operationalInstanceRiskScale->getTargetedValue(),
                    'isHidden' => $operationalRiskScaleType->isHidden(),
                ];
            }

            $result[] = [
                'id' => $operationalInstanceRisk->getId(),
                'rolfRisk' => $operationalInstanceRisk->getRolfRisk()?->getId(),
                'label' . $anrLanguage => $operationalInstanceRisk->getRiskCacheLabel($anrLanguage),
                'description' . $anrLanguage => $operationalInstanceRisk->getRiskCacheDescription($anrLanguage),
                'context' => $operationalInstanceRisk->getContext(),
                'owner' => $operationalInstanceRisk->getInstanceRiskOwner()
                    ? $operationalInstanceRisk->getInstanceRiskOwner()->getName()
                    : '',
                'netProb' => $operationalInstanceRisk->getNetProb(),
                'brutProb' => $operationalInstanceRisk->getBrutProb(),
                'targetedProb' => $operationalInstanceRisk->getTargetedProb(),
                'scales' => $scalesData,
                'cacheNetRisk' => $operationalInstanceRisk->getCacheNetRisk(),
                'cacheBrutRisk' => $operationalInstanceRisk->getCacheBrutRisk(),
                'cacheTargetedRisk' => $operationalInstanceRisk->getCacheTargetedRisk(),
                'kindOfMeasure' => $operationalInstanceRisk->getKindOfMeasure(),
                'comment' => $operationalInstanceRisk->getComment(),
                'specific' => $operationalInstanceRisk->getSpecific(),
                't' => $operationalInstanceRisk->isTreated(),
                'position' => $operationalInstanceRisk->getInstance()->getPosition(),
                'instanceInfos' => [
                    'id' => $operationalInstanceRisk->getInstance()->getId(),
                    'scope' => $operationalInstanceRisk->getInstance()->getObject()->getScope(),
                    'name' . $anrLanguage => $operationalInstanceRisk->getInstance()->getName($anrLanguage),
                ],
                'recommendations' => implode(',', $recommendationUuids),
            ];
        }

        return $result;
    }

    public function createSpecificOperationalInstanceRisk(Entity\Anr $anr, array $data): Entity\InstanceRiskOp
    {
        /** @var Entity\Instance $instance */
        $instance = $this->instanceTable->findByIdAndAnr($data['instance'], $anr);

        if ($data['source'] === self::CREATION_SOURCE_NEW_RISK) {
            $rolfRisk = (new Entity\RolfRisk())
                ->setAnr($anr)
                ->setCode($data['code'])
                ->setLabels(['label' . $anr->getLanguage() => $data['label']])
                ->setDescriptions(['description' . $anr->getLanguage() => $data['description'] ?? ''])
                ->setCreator($this->connectedUser->getFirstname() . ' ' . $this->connectedUser->getLastname());
            $this->rolfRiskTable->save($rolfRisk, true);
        } else {
            /** @var Entity\RolfRisk $rolfRisk */
            $rolfRisk = $this->rolfRiskTable->findById((int)$data['risk']);
            $operationalInstanceRisk = $this->instanceRiskOpTable->findByAnrInstanceAndRolfRisk(
                $anr,
                $instance,
                $rolfRisk
            );
            if ($operationalInstanceRisk !== null) {
                throw new Exception('This risk already exists in this instance', 412);
            }
        }

        $operationalInstanceRisk = (new Entity\InstanceRiskOp())
            ->setAnr($anr)
            ->setRolfRisk($rolfRisk)
            ->setInstance($instance)
            ->setObject($instance->getObject())
            ->setIsSpecific(true)
            ->setRiskCacheCode($rolfRisk->getCode())
            ->setRiskCacheLabels($rolfRisk->getLabels())
            ->setRiskCacheDescriptions($rolfRisk->getDescriptions())
            ->setCreator($this->connectedUser->getEmail());

        $operationalRiskScaleTypes = $this->operationalRiskScaleTypeTable->findByAnrAndScaleType(
            $instance->getAnr(),
            CoreEntity\OperationalRiskScaleSuperClass::TYPE_IMPACT
        );
        foreach ($operationalRiskScaleTypes as $operationalRiskScaleType) {
            $operationalInstanceRiskScale = (new Entity\OperationalInstanceRiskScale())
                ->setAnr($anr)
                ->setOperationalInstanceRisk($operationalInstanceRisk)
                ->setOperationalRiskScaleType($operationalRiskScaleType)
                ->setCreator($this->connectedUser->getEmail());
            $this->operationalInstanceRiskScaleTable->save($operationalInstanceRiskScale, false);
        }

        $this->instanceRiskOpTable->save($operationalInstanceRisk);

        return $operationalInstanceRisk;
    }

    public function createInstanceRisksOp(Entity\Instance $instance, Entity\MonarcObject $monarcObject): void
    {
        if ($monarcObject->getRolfTag() === null || !$monarcObject->getAsset()->isPrimary()) {
            return;
        }

        foreach ($monarcObject->getRolfTag()->getRisks() as $rolfRisk) {
            $this->createInstanceRiskOpWithScales($instance, $monarcObject, $rolfRisk);
        }

        $this->instanceRiskOpTable->flush();
    }

    /** The objects are created and persisted but not saved in the DB. */
    public function createInstanceRiskOpWithScales(
        Entity\Instance $instance,
        Entity\MonarcObject $object,
        Entity\RolfRisk $rolfRisk
    ): Entity\InstanceRiskOp {
        $instanceRiskOp = $this->createInstanceRiskOpObject($instance, $object, $rolfRisk);

        if (empty($this->operationalRiskImpactScales)) {
            /** @var Entity\OperationalRiskScaleType[] $operationalRiskScaleTypes */
            $this->operationalRiskImpactScales = $this->operationalRiskScaleTypeTable->findByAnrAndScaleType(
                $instance->getAnr(),
                CoreEntity\OperationalRiskScaleSuperClass::TYPE_IMPACT
            );
        }
        foreach ($this->operationalRiskImpactScales as $operationalRiskScaleType) {
            $this->createOperationalInstanceRiskScaleObject($instanceRiskOp, $operationalRiskScaleType);
        }

        return $instanceRiskOp;
    }

    public function createInstanceRiskOpObject(
        Entity\Instance $instance,
        Entity\MonarcObject $object,
        ?Entity\RolfRisk $rolfRisk,
        array $data = []
    ): Entity\InstanceRiskOp {
        /** @var Entity\Anr $anr */
        $anr = $instance->getAnr();
        /** @var Entity\InstanceRiskOp $instanceRiskOp */
        $instanceRiskOp = (new Entity\InstanceRiskOp())
            ->setAnr($instance->getAnr())
            ->setInstance($instance)
            ->setObject($object)
            ->setRolfRisk($rolfRisk)
            ->setRiskCacheCode($rolfRisk ? $rolfRisk->getCode() : $data['riskCacheCode'])
            ->setRiskCacheLabels($rolfRisk ? [
                'riskCacheLabel1' => $rolfRisk->getLabel(1),
                'riskCacheLabel2' => $rolfRisk->getLabel(2),
                'riskCacheLabel3' => $rolfRisk->getLabel(3),
                'riskCacheLabel4' => $rolfRisk->getLabel(4),
            ] : ['riskCacheLabel' . $anr->getLanguage() => $data['riskCacheLabel']])
            ->setRiskCacheDescriptions($rolfRisk ? [
                'riskCacheDescription1' => $rolfRisk->getDescription(1),
                'riskCacheDescription2' => $rolfRisk->getDescription(2),
                'riskCacheDescription3' => $rolfRisk->getDescription(3),
                'riskCacheDescription4' => $rolfRisk->getDescription(4),
            ] : ['riskCacheDescription' . $anr->getLanguage() => $data['riskCacheDescription']])
            ->setCreator($this->connectedUser->getEmail());

        $this->instanceRiskOpTable->save($instanceRiskOp, false);

        return $instanceRiskOp;
    }

    public function updateScaleValue(Entity\Anr $anr, int $id, array $data): Entity\InstanceRiskOp
    {
        /** @var Entity\InstanceRiskOp $operationalInstanceRisk */
        $operationalInstanceRisk = $this->instanceRiskOpTable->findByIdAndAnr($id, $anr);
        /** @var Entity\OperationalInstanceRiskScale $operationInstanceRiskScale */
        $operationInstanceRiskScale = $this->operationalInstanceRiskScaleTable->findByIdAndAnr(
            (int)$data['instanceRiskScaleId'],
            $anr
        );

        if (isset($data['netValue']) && $operationInstanceRiskScale->getNetValue() !== (int)$data['netValue']) {
            $this->verifyScaleValue($operationInstanceRiskScale, (int)$data['netValue']);
            $operationInstanceRiskScale->setNetValue((int)$data['netValue']);
        }
        if (isset($data['brutValue']) && $operationInstanceRiskScale->getBrutValue() !== (int)$data['brutValue']) {
            $this->verifyScaleValue($operationInstanceRiskScale, (int)$data['brutValue']);
            $operationInstanceRiskScale->setBrutValue((int)$data['brutValue']);
        }
        if (isset($data['targetedValue'])
            && $operationInstanceRiskScale->getTargetedValue() !== (int)$data['targetedValue']
        ) {
            $this->verifyScaleValue($operationInstanceRiskScale, (int)$data['targetedValue']);
            $operationInstanceRiskScale->setTargetedValue((int)$data['targetedValue']);
        }

        $operationInstanceRiskScale->setUpdater($this->connectedUser->getEmail());

        $this->updateRiskCacheValues($operationalInstanceRisk);

        $this->operationalInstanceRiskScaleTable->save($operationInstanceRiskScale);

        return $operationalInstanceRisk;
    }

    public function update(Entity\Anr $anr, int $id, array $data): Entity\InstanceRiskOp
    {
        /** @var Entity\InstanceRiskOp $operationalInstanceRisk */
        $operationalInstanceRisk = $this->instanceRiskOpTable->findByIdAndAnr($id, $anr);

        $likelihoodScale = $this->scalesCacheHelper->getCachedLikelihoodScale($anr);
        if (isset($data['kindOfMeasure'])) {
            $operationalInstanceRisk->setKindOfMeasure((int)$data['kindOfMeasure']);
        }
        if (isset($data['comment'])) {
            $operationalInstanceRisk->setComment($data['comment']);
        }
        if (isset($data['netProb']) && $operationalInstanceRisk->getNetProb() !== $data['netProb']) {
            $this->verifyScaleProbabilityValue((int)$data['netProb'], $likelihoodScale);
            $operationalInstanceRisk->setNetProb((int)$data['netProb']);
        }
        if (isset($data['brutProb']) && $operationalInstanceRisk->getBrutProb() !== $data['brutProb']) {
            $this->verifyScaleProbabilityValue((int)$data['brutProb'], $likelihoodScale);
            $operationalInstanceRisk->setBrutProb((int)$data['brutProb']);
        }
        if (isset($data['targetedProb']) && $operationalInstanceRisk->getTargetedProb() !== $data['targetedProb']) {
            $this->verifyScaleProbabilityValue((int)$data['targetedProb'], $likelihoodScale);
            $operationalInstanceRisk->setTargetedProb((int)$data['targetedProb']);
        }
        if (isset($data['owner'])) {
            $this->instanceRiskOwnerService->processRiskOwnerNameAndAssign(
                (string)$data['owner'],
                $operationalInstanceRisk
            );
        }
        if (isset($data['context']) && (string)$data['context'] !== $operationalInstanceRisk->getContext()) {
            $operationalInstanceRisk->setContext($data['context']);
        }

        $operationalInstanceRisk->setUpdater($this->connectedUser->getEmail());

        $this->updateRiskCacheValues($operationalInstanceRisk);

        $this->instanceRiskOpTable->save($operationalInstanceRisk);

        $this->updateInstanceRiskRecommendationsPositions($operationalInstanceRisk);

        return $operationalInstanceRisk;
    }

    public function updateRiskCacheValues(Entity\InstanceRiskOp $operationalInstanceRisk): void
    {
        foreach (['Brut', 'Net', 'Targeted'] as $valueType) {
            $max = -1;
            $probVal = $operationalInstanceRisk->{'get' . $valueType . 'Prob'}();
            if ($probVal !== -1) {
                foreach ($operationalInstanceRisk->getOperationalInstanceRiskScales() as $riskScale) {
                    if (!$riskScale->getOperationalRiskScaleType()->isHidden()) {
                        $scaleValue = $riskScale->{'get' . $valueType . 'Value'}();
                        if ($scaleValue > -1 && ($probVal * $scaleValue) > $max) {
                            $max = $probVal * $scaleValue;
                        }
                    }
                }
            }

            if ($operationalInstanceRisk->{'getCache' . $valueType . 'Risk'}() !== $max) {
                $operationalInstanceRisk
                    ->setUpdater($this->connectedUser->getEmail())
                    ->{'setCache' . $valueType . 'Risk'}($max);
                $this->instanceRiskOpTable->save($operationalInstanceRisk, false);
            }
        }
    }

    public function getOperationalRisksInCsv(Entity\Anr $anr, int $instanceId = null, array $params = []): string
    {
        $instancesIds = [];
        if ($instanceId !== null) {
            /** @var Entity\Instance $instance */
            $instance = $this->instanceTable->findByIdAndAnr($instanceId, $anr);
            $instancesIds = $instance->getSelfAndChildrenIds();
        }
        $anrLanguage = $anr->getLanguage();

        /** @var Entity\OperationalRiskScaleType[] $operationalRiskScaleTypes */
        $operationalRiskScaleTypes = $this->operationalRiskScaleTypeTable->findByAnrAndScaleType(
            $anr,
            CoreEntity\OperationalRiskScaleSuperClass::TYPE_IMPACT
        );

        $tableHeaders = [
            'instanceData' => $this->translateService->translate('Asset', $anrLanguage),
            'label' => $this->translateService->translate('Risk description', $anrLanguage),
        ];

        if ($anr->showRolfBrut()) {
            $translatedRiskValueDescription = $this->translateService->translate('Inherent risk', $anrLanguage);
            $tableHeaders['brutProb'] = $this->translateService->translate('Prob.', $anrLanguage)
                . '(' . $translatedRiskValueDescription . ')';
            foreach ($operationalRiskScaleTypes as $operationalRiskScaleType) {
                $label = $operationalRiskScaleType->getLabel();
                $tableHeaders[$label . ' (' . $translatedRiskValueDescription . ')'] = $label . ' ('
                    . $translatedRiskValueDescription . ')';
            }
            $tableHeaders['cacheBrutRisk'] = $translatedRiskValueDescription;
        }

        $translatedNetRiskDescription = $this->translateService->translate('Net risk', $anrLanguage);
        $tableHeaders['netProb'] = $this->translateService->translate('Prob.', $anrLanguage) . '('
            . $translatedNetRiskDescription . ')';
        foreach ($operationalRiskScaleTypes as $operationalRiskScaleType) {
            $label = $operationalRiskScaleType->getLabel();
            $tableHeaders[$label . ' (' . $translatedNetRiskDescription . ')'] = $label . ' ('
                . $translatedNetRiskDescription . ')';
        }
        $tableHeaders['cacheNetRisk'] = $translatedNetRiskDescription;
        $tableHeaders['comment'] = $this->translateService->translate('Existing controls', $anrLanguage);
        $tableHeaders['kindOfMeasure'] = $this->translateService->translate('Treatment', $anrLanguage);
        $tableHeaders['cacheTargetedRisk'] = $this->translateService->translate('Residual risk', $anrLanguage);
        $tableHeaders['owner'] = $this->translateService->translate('Risk owner', $anrLanguage);
        $tableHeaders['context'] = $this->translateService->translate('Risk context', $anrLanguage);
        $tableHeaders['recommendations'] = $this->translateService->translate('Recommendations', $anrLanguage);
        $tableHeaders['referentials'] = $this->translateService->translate('Security referentials', $anrLanguage);

        $output = implode(';', array_values($tableHeaders)) . "\n";

        /* CSV export is done for all the risks. */
        unset($params['limit']);

        $operationalInstanceRisks = $this->instanceRiskOpTable->findByAnrInstancesAndFilterParams(
            $anr,
            $instancesIds,
            $params
        );
        foreach ($operationalInstanceRisks as $operationalInstanceRisk) {
            $values = [
                $operationalInstanceRisk->getInstance()->getName($anrLanguage),
                $operationalInstanceRisk->getRiskCacheLabel($anrLanguage),
            ];
            if ($anr->showRolfBrut()) {
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
            $values[] = CoreEntity\InstanceRiskOpSuperClass::getAvailableMeasureTypes()[
                $operationalInstanceRisk->getKindOfMeasure()
            ];
            $values[] = $operationalInstanceRisk->getCacheTargetedRisk() === -1
                ? $operationalInstanceRisk->getCacheNetRisk()
                : $operationalInstanceRisk->getCacheTargetedRisk();
            $values[] = $operationalInstanceRisk->getInstanceRiskOwner()?->getName();
            $values[] = $operationalInstanceRisk->getContext();
            $values[] = $this->getCsvRecommendations($operationalInstanceRisk);
            $values[] = $this->getCsvMeasures($anrLanguage, $operationalInstanceRisk);


            $output .= '"';
            $search = ['"'];
            $replace = ["'"];
            $output .= implode('";"', str_replace($search, $replace, $values));
            $output .= "\"\r\n";
        }

        return $output;
    }

    public function delete(Entity\Anr $anr, int $id): void
    {
        /** @var Entity\InstanceRiskOp $operationalInstanceRisk */
        $operationalInstanceRisk = $this->instanceRiskOpTable->findByIdAndAnr($id, $anr);
        if (!$operationalInstanceRisk->isSpecific()) {
            throw new Exception('Only specific risks can be deleted.', 412);
        }

        $this->instanceRiskOpTable->remove($operationalInstanceRisk);

        $this->processRemovedInstanceRiskRecommendationsPositions($operationalInstanceRisk);
    }

    /** The object is created and persisted, but not saved in the DB. */
    public function createOperationalInstanceRiskScaleObject(
        Entity\InstanceRiskOp $instanceRiskOp,
        Entity\OperationalRiskScaleType $operationalRiskScaleType
    ): Entity\OperationalInstanceRiskScale {
        $operationalInstanceRiskScale = (new Entity\OperationalInstanceRiskScale())
            ->setAnr($instanceRiskOp->getAnr())
            ->setOperationalInstanceRisk($instanceRiskOp)
            ->setOperationalRiskScaleType($operationalRiskScaleType)
            ->setCreator($this->connectedUser->getEmail());
        $this->operationalInstanceRiskScaleTable->save($operationalInstanceRiskScale, false);

        return $operationalInstanceRiskScale;
    }

    protected function getCsvRecommendations(Entity\InstanceRiskOp $operationalInstanceRisk): string
    {
        $csvData = [];
        foreach ($operationalInstanceRisk->getRecommendationRisks() as $recommendationRisk) {
            $recommendation = $recommendationRisk->getRecommendation();
            $csvData[] = $recommendation->getCode() . ' - ' . $recommendation->getDescription();
        }

        return implode("\n", $csvData);
    }

    protected function getCsvMeasures(int $anrLanguage, Entity\InstanceRiskOp $operationalInstanceRisk): string
    {
        $csvData = [];
        if ($operationalInstanceRisk->getRolfRisk() !== null) {
            foreach ($operationalInstanceRisk->getRolfRisk()->getMeasures() as $measure) {
                $csvData[] = '[' . $measure->getReferential()->getLabel($anrLanguage) . '] '
                    . $measure->getCode() . ' - ' . $measure->getLabel($anrLanguage);
            }
        }

        return implode("\n", $csvData);
    }
}
