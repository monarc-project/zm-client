<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2023 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Doctrine\ORM\EntityNotFoundException;
use Monarc\Core\Exception\Exception;
use Monarc\Core\InputFormatter\FormattedInputParams;
use Monarc\Core\Model\Entity\AmvSuperClass;
use Monarc\Core\Model\Entity\InstanceRiskSuperClass;
use Monarc\Core\Model\Entity\UserSuperClass;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\Core\Service\Interfaces\PositionUpdatableServiceInterface;
use Monarc\Core\Service\Traits\PositionUpdateTrait;
use Monarc\FrontOffice\Model\Entity;
use Monarc\FrontOffice\Model\Table as DeprecatedTable;
use Monarc\FrontOffice\Table;

class AnrAmvService implements PositionUpdatableServiceInterface
{
    use PositionUpdateTrait;

    private Table\AmvTable $amvTable;

    private Table\AssetTable $assetTable;

    private Table\ThreatTable $threatTable;

    private Table\ThemeTable $themeTable;

    private Table\VulnerabilityTable $vulnerabilityTable;

    private Table\InstanceRiskTable $instanceRiskTable;

    private DeprecatedTable\MeasureTable $measureTable;

    private AnrAssetService $anrAssetService;

    private AnrThreatService $anrThreatService;

    private AnrThemeService $anrThemeService;

    private AnrVulnerabilityService $anrVulnerabilityService;

    private AnrInstanceRiskService $anrInstanceRiskService;

    private UserSuperClass $connectedUser;
    private DeprecatedTable\ReferentialTable $referentialTable;

    public function __construct(
        Table\AmvTable $amvTable,
        Table\AssetTable $assetTable,
        Table\ThreatTable $threatTable,
        Table\ThemeTable $themeTable,
        Table\VulnerabilityTable $vulnerabilityTable,
        Table\InstanceRiskTable $instanceRiskTable,
        DeprecatedTable\MeasureTable $measureTable,
        DeprecatedTable\ReferentialTable $referentialTable,
        AnrAssetService $anrAssetService,
        AnrThreatService $anrThreatService,
        AnrVulnerabilityService $anrVulnerabilityService,
        AnrInstanceRiskService $anrInstanceRiskService,
        ConnectedUserService $connectedUserService
    ) {
        $this->amvTable = $amvTable;
        $this->assetTable = $assetTable;
        $this->threatTable = $threatTable;
        $this->themeTable = $themeTable;
        $this->vulnerabilityTable = $vulnerabilityTable;
        $this->instanceRiskTable = $instanceRiskTable;
        $this->measureTable = $measureTable;
        $this->referentialTable = $referentialTable;
        $this->anrAssetService = $anrAssetService;
        $this->anrThreatService = $anrThreatService;
        $this->anrVulnerabilityService = $anrVulnerabilityService;
        $this->anrInstanceRiskService = $anrInstanceRiskService;
        $this->connectedUser = $connectedUserService->getConnectedUser();
    }

    public function getList(FormattedInputParams $params): array
    {
        $result = [];

        /** @var Entity\Amv[] $amvs */
        $amvs = $this->amvTable->findByParams($params);
        foreach ($amvs as $amv) {
            $result[] = $this->prepareAmvDataResult($amv);
        }

        return $result;
    }

    public function getCount($params): int
    {
        return $this->amvTable->countByParams($params);
    }

    public function getAmvData(Entity\Anr $anr, string $uuid): array
    {
        /** @var Entity\Amv $amv */
        $amv = $this->amvTable->findByUuidAndAnr($uuid, $anr);

        return $this->prepareAmvDataResult($amv);
    }

    public function create(Entity\Anr $anr, array $data, bool $saveInDb = true): Entity\Amv
    {
        if ($this->amvTable
            ->findByAmvItemsUuidsAndAnr($data['asset'], $data['threat'], $data['vulnerability'], $anr) !== null
        ) {
            throw new Exception('The informational risk already exists.', 412);
        }

        /** @var Entity\Asset $asset */
        $asset = $this->assetTable->findByUuidAndAnr($data['asset'], $anr);
        /** @var Entity\Threat $threat */
        $threat = $this->threatTable->findByUuidAndAnr($data['threat'], $anr);
        /** @var Entity\Vulnerability $vulnerability */
        $vulnerability = $this->vulnerabilityTable->findByUuidAndAnr($data['vulnerability'], $anr);

        $amv = (new Entity\Amv())
            ->setAnr($anr)
            ->setAsset($asset)
            ->setThreat($threat)
            ->setVulnerability($vulnerability)
            ->setCreator($this->connectedUser->getEmail());
        if (isset($data['status'])) {
            $amv->setStatus($data['status']);
        }

        foreach ($data['measures'] ?? [] as $measureUuid) {
            $amv->addMeasure($this->measureTable->findByAnrAndUuid($anr, $measureUuid));
        }

        $this->updatePositions($amv, $this->amvTable, $data);

        $this->createInstanceRiskForInstances($asset, $amv);

        $this->amvTable->save($amv, $saveInDb);

        return $amv;
    }

    public function update(Entity\Anr $anr, string $uuid, array $data): Entity\Amv
    {
        /** @var Entity\Amv $amv */
        $amv = $this->amvTable->findByUuidAndAnr($uuid, $anr);

        foreach ($amv->getMeasures() as $measure) {
            $linkedMeasuresUuidKey = array_search($measure->getUuid(), $data['measures'], true);
            if ($linkedMeasuresUuidKey !== false) {
                unset($data['measures'][$linkedMeasuresUuidKey]);
            } else {
                $amv->removeMeasure($measure);
            }
        }
        foreach ($data['measures'] as $measure) {
            $amv->addMeasure($this->measureTable->findByAnrAndUuid($anr, $measure));
        }

        $amv->setUpdater($this->connectedUser->getEmail());

        $isThreatChanged = $this->isThreatChanged($data, $amv);
        if ($isThreatChanged) {
            /** @var Entity\Threat $threat */
            $threat = $this->threatTable->findByUuidAndAnr($data['threat'], $anr);
            $amv->setThreat($threat);
        }

        $isVulnerabilityChanged = $this->isVulnerabilityChanged($data, $amv);
        if ($isVulnerabilityChanged) {
            /** @var Entity\Vulnerability $vulnerability */
            $vulnerability = $this->vulnerabilityTable->findByUuidAndAnr($data['vulnerability'], $anr);
            $amv->setVulnerability($vulnerability);
        }
        if ($isThreatChanged || $isVulnerabilityChanged) {
            foreach ($amv->getInstanceRisks() as $instanceRisk) {
                $instanceRisk->setThreat($amv->getThreat());
                $instanceRisk->setVulnerability($amv->getVulnerability());
            }
        }

        $this->amvTable->save($amv);

        return $amv;
    }

    public function patch(Entity\Anr $anr, string $uuid, array $data): Entity\Amv
    {
        /** @var Entity\Amv $amv */
        $amv = $this->amvTable->findByUuidAndAnr($uuid, $anr);
        if (isset($data['status'])) {
            $amv->setStatus((int)$data['status'])->setUpdater($this->connectedUser->getEmail());
            $this->amvTable->save($amv);
        }

        return $amv;
    }

    /**
     * Import of instance risks.
     *
     * @return string[] Created Amvs' uuids.
     */
    public function createAmvItems(Entity\Anr $anr, array $data): array
    {
        $createdAmvsUuids = [];
        foreach ($data as $amvData) {
            if (!isset($amvData['asset']['uuid'], $amvData['threat']['uuid'], $amvData['vulnerability']['uuid'])
                || $this->amvTable->findByAmvItemsUuidsAndAnr(
                    $amvData['asset']['uuid'],
                    $amvData['threat']['uuid'],
                    $amvData['vulnerability']['uuid'],
                    $anr
                ) !== null
            ) {
                continue;
            }

            $asset = $this->getOrCreateAssetObject($anr, $amvData['asset']);
            $threat = $this->getOrCreateThreatObject($anr, $amvData['threat']);
            $vulnerability = $this->getOrCreateVulnerabilityObject($anr, $amvData['vulnerability']);

            $amv = (new Entity\Amv())
                ->setAnr($anr)
                ->setAsset($asset)
                ->setThreat($threat)
                ->setVulnerability($vulnerability)
                ->setCreator($this->connectedUser->getEmail());

            $this->createInstanceRiskForInstances($asset, $amv);

            $this->amvTable->save($amv);

            $createdAmvsUuids[] = $amv->getUuid();
        }

        return $createdAmvsUuids;
    }

    /**
     * Links amv of destination to source depending on the measures_measures (map referential).
     */
    public function createLinkedAmvs(
        Entity\Anr $anr,
        string $sourceReferentialUuid,
        string $destinationReferentialUuid
    ): void {
        /** @var Entity\Referential $referential */
        $referential = $this->referentialTable->findByAnrAndUuid($anr, $destinationReferentialUuid);
        foreach ($referential->getMeasures() as $destinationMeasure) {
            foreach ($destinationMeasure->getLinkedMeasures() as $measureLink) {
                if ($measureLink->getReferential()->getUuid() === $sourceReferentialUuid) {
                    foreach ($measureLink->getAmvs() as $amv) {
                        $destinationMeasure->addAmv($amv);
                    }
                    $this->measureTable->save($destinationMeasure, false);
                }
            }
        }
        $this->measureTable->getDb()->flush();
    }

    public function delete(Entity\Anr $anr, string $uuid): void
    {
        /** @var Entity\Amv $amv */
        $amv = $this->amvTable->findByUuidAndAnr($uuid, $anr);
        $this->resetInstanceRisksRelation($amv);
        $this->amvTable->remove($amv);
    }

    public function deleteList(Entity\Anr $anr, array $data): void
    {
        /** @var Entity\Amv $amv */
        foreach ($this->amvTable->findByUuidsAndAnr($data, $anr) as $amv) {
            $this->resetInstanceRisksRelation($amv);
            $this->amvTable->remove($amv);
        }
    }

    private function getOrCreateAssetObject(Entity\Anr $anr, array $assetData): Entity\Asset
    {
        if (!empty($assetData['uuid'])) {
            try {
                /** @var Entity\Asset $asset */
                $asset = $this->assetTable->findByUuidAndAnr($assetData['uuid'], $anr);

                return $asset;
            } catch (EntityNotFoundException $e) {
            }
        }

        return $this->anrAssetService->create($anr, $assetData, false);
    }

    private function getOrCreateThreatObject(Entity\Anr $anr, array $threatData): Entity\Threat
    {
        if (!empty($threatData['uuid'])) {
            try {
                /** @var Entity\Threat $threat */
                $threat = $this->threatTable->findByUuidAndAnr($threatData['uuid'], $anr);

                return $threat;
            } catch (EntityNotFoundException $e) {
            }
        }

        if (isset($threatData['theme'])) {
            $threatData['theme'] = $this->themeTable->findById((int)$threatData['theme']);
        }

        return $this->anrThreatService->create($anr, $threatData, false);
    }

    private function getOrCreateVulnerabilityObject(Entity\Anr $anr, array $vulnerabilityData): Entity\Vulnerability
    {
        if (!empty($vulnerabilityData['uuid'])) {
            try {
                /** @var Entity\Vulnerability $vulnerability */
                $vulnerability = $this->vulnerabilityTable->findByUuidAndAnr($vulnerabilityData['uuid'], $anr);

                return $vulnerability;
            } catch (EntityNotFoundException $e) {
            }
        }

        return $this->anrVulnerabilityService->create($anr, $vulnerabilityData);
    }

    /**
     * Created instance risks based on the newly created AMV for the instances based on the linked asset.
     */
    private function createInstanceRiskForInstances(Entity\Asset $asset, Entity\Amv $amv): void
    {
        foreach ($asset->getInstances() as $instance) {
            $this->anrInstanceRiskService->createInstanceRisk($instance, $amv);
        }
    }

    private function isThreatChanged(array $data, AmvSuperClass $amv): bool
    {
        return $amv->getThreat()->getUuid() !== $data['threat'];
    }

    private function isVulnerabilityChanged(array $data, AmvSuperClass $amv): bool
    {
        return $amv->getVulnerability()->getUuid() !== $data['vulnerability'];
    }

    private function resetInstanceRisksRelation(Entity\Amv $amv): void
    {
        foreach ($amv->getInstanceRisks() as $instanceRisk) {
            $instanceRisk->setAmv(null)
                ->setSpecific(InstanceRiskSuperClass::TYPE_SPECIFIC)
                ->setUpdater($this->connectedUser->getEmail());

            $this->instanceRiskTable->save($instanceRisk);

            // TODO: remove it when double fields relation is removed.
            $this->instanceRiskTable->refresh($instanceRisk);
            $this->instanceRiskTable->save($instanceRisk->setAnr($amv->getAnr()));
        }
    }

    private function prepareAmvDataResult(Entity\Amv $amv, bool $includePositionFields = false): array
    {
        $measures = [];
        foreach ($amv->getMeasures() as $measure) {
            $referential = $measure->getReferential();
            $measures[] = [
                'uuid' => $measure->getUuid(),
                'code' => $measure->getCode(),
                'label1' => $measure->getLabel(1),
                'label2' => $measure->getLabel(2),
                'label3' => $measure->getLabel(3),
                'label4' => $measure->getLabel(4),
                'referential' => [
                    'uuid' => $referential->getUuid(),
                    'label1' => $referential->getLabel(1),
                    'label2' => $referential->getLabel(2),
                    'label3' => $referential->getLabel(3),
                    'label4' => $referential->getLabel(4),
                ]
            ];
        }

        $result = array_merge([
            'uuid' => $amv->getUuid(),
            'measures' => $measures,
            'status' => $amv->getStatus(),
            'position' => $amv->getPosition(),
        ], $this->getAmvRelationsData($amv));

        if ($includePositionFields) {
            $result['implicitPosition'] = 1;
            if ($amv->getPosition() > 1) {
                $maxPositionByAsset = $this->amvTable->findMaxPosition($amv->getImplicitPositionRelationsValues());
                if ($maxPositionByAsset === $amv->getPosition()) {
                    $result['implicitPosition'] = 2;
                } else {
                    $previousAmv = $this->amvTable->findByAssetAndPosition($amv->getAsset(), $amv->getPosition() - 1);
                    if ($previousAmv !== null) {
                        $result['implicitPosition'] = 3;
                        $result['previous'] = array_merge([
                            'uuid' => $previousAmv->getUuid(),
                            'position' => $previousAmv->getPosition(),
                        ], $this->getAmvRelationsData($previousAmv));
                    }
                }
            }
        }

        return $result;
    }

    private function getAmvRelationsData(Entity\Amv $amv): array
    {
        $asset = $amv->getAsset();
        $threat = $amv->getThreat();
        $vulnerability = $amv->getVulnerability();

        return [
            'asset' => array_merge([
                'uuid' => $asset->getUuid(),
                'code' => $asset->getCode(),
            ], $asset->getLabels()),
            'threat' => array_merge([
                'uuid' => $threat->getUuid(),
                'code' => $threat->getCode(),
            ], $threat->getLabels()),
            'vulnerability' => array_merge([
                'uuid' => $vulnerability->getUuid(),
                'code' => $vulnerability->getCode(),
            ], $vulnerability->getLabels()),
        ];
    }
}
