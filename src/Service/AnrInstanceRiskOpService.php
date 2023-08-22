<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2023 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Doctrine\ORM\EntityNotFoundException;
use Doctrine\ORM\ORMException;
use Monarc\Core\Exception\Exception;
use Monarc\Core\Model\Entity as CoreEntity;
use Monarc\Core\Model\Entity\AnrSuperClass;
use Monarc\Core\Model\Entity\InstanceRiskOpSuperClass;
use Monarc\Core\Model\Entity\InstanceRiskOwnerSuperClass;
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
use Monarc\FrontOffice\Model\Entity as FrontOfficeEntity;
use Monarc\FrontOffice\Model\Entity\Anr;
use Monarc\FrontOffice\Model\Entity\Instance;
use Monarc\FrontOffice\Model\Entity\InstanceRiskOp;
use Monarc\FrontOffice\Model\Entity\OperationalInstanceRiskScale;
use Monarc\FrontOffice\Model\Entity\OperationalRiskScale;
use Monarc\FrontOffice\Model\Entity\RolfRisk;
use Monarc\FrontOffice\Model\Entity\InstanceRiskOwner;
use Monarc\FrontOffice\Model\Entity\Translation;
use Monarc\FrontOffice\Model\Table\InstanceRiskOpTable;
use Monarc\FrontOffice\Table\InstanceRiskOwnerTable;
use Monarc\FrontOffice\Model\Table\InstanceTable;
use Monarc\FrontOffice\Table\OperationalInstanceRiskScaleTable;
use Monarc\FrontOffice\Table\OperationalRiskScaleTable;
use Monarc\FrontOffice\Table\OperationalRiskScaleTypeTable;
use Monarc\FrontOffice\Model\Table\RecommandationTable;
use Monarc\FrontOffice\Model\Table\RecommandationRiskTable;
use Monarc\FrontOffice\Model\Table\RolfRiskTable;
use Monarc\FrontOffice\Model\Table\RolfTagTable;
use Monarc\FrontOffice\Table\TranslationTable;
use Monarc\FrontOffice\Service\Traits\RecommendationsPositionsUpdateTrait;

class AnrInstanceRiskOpService extends InstanceRiskOpService
{
    use RecommendationsPositionsUpdateTrait;

    /** @var InstanceRiskOpTable $instanceRiskOpTable */
    protected CoreInstanceRiskOpTable $instanceRiskOpTable;

    protected RolfRiskTable $rolfRiskTable;

    protected RecommandationTable $recommendationTable;

    protected RecommandationRiskTable $recommendationRiskTable;

    protected InstanceRiskOwnerTable $instanceRiskOwnerTable;

    public function __construct(
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
        InstanceRiskOwnerTable $instanceRiskOwnerTable,
        RecommandationTable $recommendationTable,
        RecommandationRiskTable $recommendationRiskTable
    ) {
        // TODO: InstanceTable is not as expected. Perhaps we need to drop the service inheritance or extend the InstanceTable.
        parent::__construct(
            $instanceTable,
            $instanceRiskOpTable,
            $operationalInstanceRiskScaleTable,
            $rolfTagTable,
            $connectedUserService,
            $translationTable,
            $translateService,
            $operationalRiskScaleTable,
            $operationalRiskScaleTypeTable,
            $configService
        );
        $this->recommendationRiskTable = $recommendationRiskTable;
        $this->rolfRiskTable = $rolfRiskTable;
        $this->recommendationTable = $recommendationTable;
        $this->instanceRiskOwnerTable = $instanceRiskOwnerTable;
    }

    /*
     public function createInstanceRisksOp(Entity\InstanceSuperClass $instance, Entity\ObjectSuperClass $object): void
    {
        if ($object->getRolfTag() === null || !$object->getAsset()->isPrimary()) {
            return;
        }

        $otherInstance = $this->instanceTable->findOneByAnrAndObjectExcludeInstance(
            $instance->getAnr(),
            $object,
            $instance
        );

        if ($otherInstance !== null && $object->isScopeGlobal()) {
            foreach ($this->instanceRiskOpTable->findByInstance($otherInstance) as $instanceRiskOp) {
                $newInstanceRiskOp = $this->getConstructedFromObjectInstanceRiskOp($instanceRiskOp)
                    ->setAnr($instance->getAnr())
                    ->setInstance($instance)
                    ->setObject($instanceRiskOp->getObject())
                    ->setRolfRisk($instanceRiskOp->getRolfRisk())
                    // TODO: this is not set on Core.
                    ->setInstanceRiskOwner($instanceRiskOp->getInstanceRiskOwner())
                    ->setCreator($this->connectedUser->getEmail());
                $this->instanceRiskOpTable->save($newInstanceRiskOp, false);

                $operationalInstanceRiskScales = $this->operationalInstanceRiskScaleTable->findByInstanceRiskOp(
                    $instanceRiskOp
                );
                foreach ($operationalInstanceRiskScales as $operationalInstanceRiskScale) {
                    $newOperationalInstanceRiskScale = $this
                        ->getConstructedFromObjectOperationalInstanceRiskScale($operationalInstanceRiskScale)
                        ->setCreator($this->connectedUser->getEmail());
                    $this->operationalInstanceRiskScaleTable->save($newOperationalInstanceRiskScale, false);
                }
            }
        } else {
            $rolfTag = $this->rolfTagTable->findById($object->getRolfTag()->getId());
            foreach ($rolfTag->getRisks() as $rolfRisk) {
                $this->createInstanceRiskOpWithScales(
                    $instance,
                    $object,
                    $rolfRisk
                );
            }
        }

        $this->instanceRiskOpTable->flush();
    }

    public function update(Entity\AnrSuperClass $anr, int $id, array $data): Entity\InstanceRiskOpSuperClass
    {
        /** @var Entity\InstanceRiskOpSuperClass $operationalInstanceRisk
        $operationalInstanceRisk = $this->instanceRiskOpTable->findByIdAndAnr($id, $anr);

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
        // TODO: missing on Core:
        if (isset($data['owner'])) {
            $this->processRiskOwnerName((string)$data['owner'], $operationalInstanceRisk);
        }
        if (isset($data['context']) && (string)$data['context'] !== $operationalInstanceRisk->getContext()) {
            $operationalInstanceRisk->setContext($data['context']);
        }

        $operationalInstanceRisk->setUpdater($this->connectedUser->getEmail());

        $this->updateRiskCacheValues($operationalInstanceRisk);

        $this->instanceRiskOpTable->save($operationalInstanceRisk);

        return $operationalInstanceRisk;
    }
     */

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
            ->setIsSpecific(true)
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

        $this->instanceRiskOpTable->save($operationalInstanceRisk, false);

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

    public function getOperationalRisks(AnrSuperClass $anr, int $instanceId = null, array $params = []): array
    {
        $instancesIds = $this->determineInstancesIdsFromParam($instanceId);

        $anrLanguage = $anr->getLanguage();

        $operationalInstanceRisks = $this->instanceRiskOpTable->findByAnrInstancesAndFilterParams(
            $anr,
            $instancesIds,
            $params
        );

        $result = [];
        $scaleTypesTranslations = $this->translationTable->findByAnrTypesAndLanguageIndexedByKey(
            $anr,
            [Translation::OPERATIONAL_RISK_SCALE_TYPE, Translation::OPERATIONAL_RISK_SCALE_COMMENT],
            $this->getAnrLanguageCode($anr)
        );
        foreach ($operationalInstanceRisks as $operationalInstanceRisk) {
            $recommendationUuids = [];
            foreach ($operationalInstanceRisk->getRecommendationRisks() as $recommendationRisk) {
                if ($recommendationRisk->getRecommandation() !== null) {
                    $recommendationUuids[] = $recommendationRisk->getRecommandation()->getUuid();
                }
            }

            $scalesData = [];
            foreach ($operationalInstanceRisk->getOperationalInstanceRiskScales() as $operationalInstanceRiskScale) {
                $operationalRiskScaleType = $operationalInstanceRiskScale->getOperationalRiskScaleType();
                $scalesData[$operationalRiskScaleType->getId()] = [
                    'instanceRiskScaleId' => $operationalInstanceRiskScale->getId(),
                    'label' => isset($scaleTypesTranslations[$operationalRiskScaleType->getLabelTranslationKey()])
                        ? $scaleTypesTranslations[$operationalRiskScaleType->getLabelTranslationKey()]->getValue()
                        : '',
                    'netValue' => $operationalInstanceRiskScale->getNetValue(),
                    'brutValue' => $operationalInstanceRiskScale->getBrutValue(),
                    'targetedValue' => $operationalInstanceRiskScale->getTargetedValue(),
                    'isHidden' => $operationalRiskScaleType->isHidden(),
                ];
            }

            $result[] = [
                'id' => $operationalInstanceRisk->getId(),
                'rolfRisk' => $operationalInstanceRisk->getRolfRisk()
                    ? $operationalInstanceRisk->getRolfRisk()->getId()
                    : null,
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
                't' => $operationalInstanceRisk->getKindOfMeasure() === InstanceRiskOp::KIND_NOT_TREATED ? 0 : 1,
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

    public function getOperationalRisksInCsv(Anr $anr, int $instanceId = null, array $params = []): string
    {
        $instancesIds = $this->determineInstancesIdsFromParam($instanceId);
        $anrLanguage = $anr->getLanguage();

        $operationalRiskScaleTypes = $this->operationalRiskScaleTypeTable->findByAnrAndScaleType(
            $anr,
            OperationalRiskScale::TYPE_IMPACT
        );
        $scaleTypesTranslations = $this->translationTable->findByAnrTypesAndLanguageIndexedByKey(
            $anr,
            [Translation::OPERATIONAL_RISK_SCALE_TYPE],
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
                $tableHeaders[$label . " (" . $translatedRiskValueDescription . ")"] = $label . " ("
                    . $translatedRiskValueDescription . ")";
            }
            $tableHeaders['cacheBrutRisk'] = $translatedRiskValueDescription;
        }

        $translatedNetRiskDescription = $this->translateService->translate('Net risk', $anrLanguage);
        $tableHeaders['netProb'] = $this->translateService->translate('Prob.', $anrLanguage) . "("
            . $translatedNetRiskDescription . ")";
        foreach ($operationalRiskScaleTypes as $operationalRiskScaleType) {
            $label = $scaleTypesTranslations[$operationalRiskScaleType->getLabelTranslationKey()]
                ->getValue();
            $tableHeaders[$label . " (" . $translatedNetRiskDescription . ")"] = $label . " ("
                . $translatedNetRiskDescription . ")";
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
            $values[] = $operationalInstanceRisk->getCacheTargetedRisk() === -1 ?
                $operationalInstanceRisk->getCacheNetRisk() :
                $operationalInstanceRisk->getCacheTargetedRisk();
            $values[] = $operationalInstanceRisk->getInstanceRiskOwner() !== null ?
                $operationalInstanceRisk->getInstanceRiskOwner()->getName() :
                null;
            $values[] = $operationalInstanceRisk->getContext();
            $values[] = $this->getCsvRecommendations($anr, $operationalInstanceRisk);
            $values[] = $this->getCsvMeasures($anrLanguage, $operationalInstanceRisk);


            $output .= '"';
            $search = ['"'];
            $replace = ["'"];
            $output .= implode('";"', str_replace($search, $replace, $values));
            $output .= "\"\r\n";
        }

        return $output;
    }

    /**
     * @throws EntityNotFoundException
     * @throws Exception
     * @throws ORMException
     */
    public function update(int $id, array $data): array
    {
        $result = parent::update($id, $data);

        /** @var InstanceRiskOp $operationalInstanceRisk */
        $operationalInstanceRisk = $this->instanceRiskOpTable->findById($id);

        $this->updateInstanceRiskRecommendationsPositions($operationalInstanceRisk);

        return $result;
    }

    public function delete(int $id): void
    {
        $operationalInstanceRisk = $this->instanceRiskOpTable->findById($id);
        if (!$operationalInstanceRisk->isSpecific()) {
            throw new Exception('Only specific risks can be deleted.', 412);
        }

        $this->instanceRiskOpTable->remove($operationalInstanceRisk);

        $this->processRemovedInstanceRiskRecommendationsPositions($operationalInstanceRisk);
    }

    /**
     * Called from parent::createInstanceRiskOpWithScales
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
     * && OperationalRiskScaleService::createOperationalRiskScaleType
     */
    public function createOperationalInstanceRiskScaleObject(
        InstanceRiskOpSuperClass $instanceRiskOp,
        OperationalRiskScaleTypeSuperClass $operationalRiskScaleType
    ): OperationalInstanceRiskScaleSuperClass {
        return (new OperationalInstanceRiskScale())
            ->setAnr($instanceRiskOp->getAnr())
            ->setOperationalInstanceRisk($instanceRiskOp)
            ->setOperationalRiskScaleType($operationalRiskScaleType)
            ->setCreator($this->connectedUser->getEmail());
    }

    protected function createInstanceRiskOwnerObject(AnrSuperClass $anr, string $ownerName): InstanceRiskOwnerSuperClass
    {
        return (new InstanceRiskOwner())
            ->setAnr($anr)
            ->setName($ownerName)
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
        foreach ($instances as $instanceId => $instance) {
            $instancesIds[] = $instanceId;
            $instancesIds = array_merge(
                $instancesIds,
                $this->extractInstancesAndTheirChildrenIds($instance->getParameterValues('children'))
            );
        }

        return $instancesIds;
    }

    private function determineInstancesIdsFromParam($instanceId): array
    {
        $instancesIds = [];
        if ($instanceId !== null) {
            $instance = $this->instanceTable->findById($instanceId);
            // TODO: remove initTree and use TreeStructureTrait::getEntityIdsWithLinkedChildren
            $this->instanceTable->initTree($instance);
            $instancesIds = $this->extractInstancesAndTheirChildrenIds([$instance->getId() => $instance]);
        }

        return $instancesIds;
    }

    protected function getCsvRecommendations(AnrSuperClass $anr, InstanceRiskOp $operationalInstanceRisk): string
    {
        $recommendationsRisks = $this->recommendationRiskTable->findByAnrAndOperationalInstanceRisk(
            $anr,
            $operationalInstanceRisk
        );
        $csvData = [];
        foreach ($recommendationsRisks as $recommendationRisk) {
            $recommendation = $recommendationRisk->getRecommandation();
            $csvData[] = $recommendation->getCode() . " - " . $recommendation->getDescription();
        }

        return implode("\n", $csvData);
    }

    protected function getCsvMeasures(int $anrLanguage, InstanceRiskOp $operationalInstanceRisk): string
    {
        $measures = $operationalInstanceRisk->getRolfRisk()
            ? $operationalInstanceRisk->getRolfRisk()->getMeasures()
            : [];
        $csvData = [];
        foreach ($measures as $measure) {
            $csvData[] = "[" . $measure->getReferential()->getLabel($anrLanguage) . "] " .
               $measure->getCode() . " - " . $measure->getLabel($anrLanguage);
        }

        return implode("\n", $csvData);
    }

    protected function getConstructedFromObjectInstanceRiskOp(
        CoreEntity\InstanceRiskOpSuperClass $instanceRiskOp
    ): CoreEntity\InstanceRiskOpSuperClass {
        return FrontOfficeEntity\InstanceRiskOp::constructFromObject($instanceRiskOp);
    }

    protected function getConstructedFromObjectOperationalInstanceRiskScale(
        CoreEntity\OperationalInstanceRiskScaleSuperClass $operationalInstanceRiskScale
    ): CoreEntity\OperationalInstanceRiskScaleSuperClass {
        return FrontOfficeEntity\OperationalInstanceRiskScale::constructFromObject($operationalInstanceRiskScale);
    }

    private function processRiskOwnerName(
        string $ownerName,
        Entity\InstanceRiskOp $operationalInstanceRisk
    ): void {
        if (empty($ownerName)) {
            $operationalInstanceRisk->setInstanceRiskOwner(null);
        } else {
            $instanceRiskOwner = $this->instanceRiskOwnerTable->findByAnrAndName(
                $operationalInstanceRisk->getAnr(),
                $ownerName
            );
            if ($instanceRiskOwner === null) {
                $instanceRiskOwner = $this->createInstanceRiskOwnerObject(
                    $operationalInstanceRisk->getAnr(),
                    $ownerName
                );

                $this->instanceRiskOwnerTable->save($instanceRiskOwner, false);

                $operationalInstanceRisk->setInstanceRiskOwner($instanceRiskOwner);
            } elseif ($operationalInstanceRisk->getInstanceRiskOwner() === null
                || $operationalInstanceRisk->getInstanceRiskOwner()->getId() !== $instanceRiskOwner->getId()
            ) {
                $operationalInstanceRisk->setInstanceRiskOwner($instanceRiskOwner);
            }
        }
    }

    private function createInstanceRiskOwnerObject(
        Entity\Anr $anr,
        string $ownerName
    ): Entity\InstanceRiskOwner {
        return (new Entity\InstanceRiskOwner())
            ->setAnr($anr)
            ->setName($ownerName)
            ->setCreator($this->connectedUser->getEmail());
    }
}
