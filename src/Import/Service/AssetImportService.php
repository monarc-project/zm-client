<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Import\Service;

use Monarc\Core\Exception\Exception;
use Monarc\Core\Entity\UserSuperClass;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Import\Helper\ImportCacheHelper;
use Monarc\FrontOffice\Entity\Amv;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Entity\Asset;
use Monarc\FrontOffice\Entity\InstanceRisk;
use Monarc\FrontOffice\Entity\Measure;
use Monarc\FrontOffice\Entity\Referential;
use Monarc\FrontOffice\Import\Processor;
use Monarc\FrontOffice\Table;
use Monarc\FrontOffice\Service\SoaCategoryService;

class AssetImportService
{
    private UserSuperClass $connectedUser;

    public function __construct(
        private Processor\AssetImportProcessor $assetImportProcessor,
        private Processor\ThreatImportProcessor $threatImportProcessor,
        private Processor\VulnerabilityImportProcessor $vulnerabilityImportProcessor,
        private Table\MeasureTable $measureTable,
        private Table\AmvTable $amvTable,
        private Table\InstanceRiskTable $instanceRiskTable,
        private Table\ReferentialTable $referentialTable,
        private ImportCacheHelper $importCacheHelper,
        private SoaCategoryService $soaCategoryService,
        ConnectedUserService $connectedUserService
    ) {
        $this->connectedUser = $connectedUserService->getConnectedUser();
    }

    public function importFromArray($monarcVersion, array $data, Anr $anr): ?Asset
    {
        if (!isset($data['type']) || $data['type'] !== 'asset') {
            return null;
        }

        if (version_compare($monarcVersion, '2.8.2') < 0) {
            throw new Exception('Import of files exported from MONARC v2.8.1 or lower are not supported.'
                . ' Please contact us for more details.');
        }

        $asset = $this->assetImportProcessor->processAssetData($anr, $data['asset']);
        /* In the new structure 'amvs' => 'informationRisks', 'vuls' => 'vulnerabilities'. */
        if (!empty($data['amvs']) || !empty($data['informationRisks'])) {
            $this->threatImportProcessor->processThreatsData($anr, $data['threats'], $data['themes'] ?? []);
            $this->vulnerabilityImportProcessor
                ->processVulnerabilitiesData($anr, $data['vuls'] ?? $data['vulnerabilities']);
            $this->processInformationRisksData($data['amvs'] ?? $data['informationRisks'], $anr, $asset);
        }

        return $asset;
    }

    // TODO: use services to create the objects.
    private function processInformationRisksData(array $amvsData, Anr $anr, Asset $asset): void
    {
        foreach ($amvsData as $amvUuid => $amvData) {
            /** @var Amv|null $amv */
            $amv = $this->amvTable->findByUuidAndAnr($amvUuid, $anr, false);
            if ($amv === null) {
                $amv = (new Amv())
                    ->setUuid($amvUuid)
                    ->setAnr($anr)
                    ->setAsset($asset)
                    ->setCreator($this->connectedUser->getEmail());

                $threat = $this->threatImportProcessor->getThreatFromCache($amvData['threat']);
                $vulnerability = $this->vulnerabilityImportProcessor->getVulnerabilityFromCache(
                    $amvData['vulnerability']
                );
                if ($threat === null || $vulnerability === null) {
                    throw new Exception(sprintf(
                        'The import file is malformed. AMV\'s "%s" threats or vulnerability was not processed before.',
                        $amvUuid
                    ));
                }

                $amv->setThreat($threat)->setVulnerability($vulnerability);

                $this->amvTable->save($amv, false);

                foreach ($asset->getInstances() as $instance) {
                    $instanceRisk = (new InstanceRisk())
                        ->setAnr($anr)
                        ->setAmv($amv)
                        ->setAsset($asset)
                        ->setInstance($instance)
                        ->setThreat($threat)
                        ->setVulnerability($vulnerability)
                        ->setCreator($this->connectedUser->getEmail());

                    $this->instanceRiskTable->save($instanceRisk, false);
                }
            }

            if (!empty($amvData['measures'])) {
                $this->processMeasuresAndReferentialData($amvData['measures'], $anr, $amv);
            }
        }

        // TODO: perhaps we can do this before the previous foreach or find another solution.
        foreach ($this->amvTable->findByAnrAndAsset($anr, $asset) as $oldAmv) {
            if (!isset($amvsData[$oldAmv->getUuid()])) {
                /** Set related instance risks to specific and delete the amvs later. */
                $instanceRisks = $oldAmv->getInstanceRisks();

                // TODO: remove the double iteration when #240 is done.
                // We do it due to multi-fields relation issue. When amv is set to null, anr is set to null as well.
                foreach ($instanceRisks as $instanceRisk) {
                    $instanceRisk->setAmv(null);
                    $instanceRisk->setAnr(null);
                    $instanceRisk->setSpecific(InstanceRisk::TYPE_SPECIFIC);
                    $this->instanceRiskTable->save($instanceRisk, false);
                }
                $this->instanceRiskTable->flush();

                foreach ($instanceRisks as $instanceRisk) {
                    $instanceRisk
                        ->setAnr($anr)
                        ->setUpdater($this->connectedUser->getEmail());
                    $this->instanceRiskTable->save($instanceRisk, false);
                }
                $this->instanceRiskTable->flush();

                $amvsToDelete[] = $oldAmv;
            }
        }

        if (!empty($amvsToDelete)) {
            $this->amvTable->removeList($amvsToDelete);
        }
    }

    private function processMeasuresAndReferentialData(array $measuresData, Anr $anr, Amv $amv): void
    {
        $languageIndex = $anr->getLanguage();
        $labelKey = 'label' . $languageIndex;
        foreach ($measuresData as $measureUuid) {
            $measure = $this->importCacheHelper->getItemFromArrayCache('measures', $measureUuid)
                ?: $this->measureTable->findByUuidAndAnr($measureUuid, $anr);
            if ($measure === null) {
                /* Backward compatibility. Prior v2.10.3 we did not set referential data when exported. */
                $referentialUuid = $data['measures'][$measureUuid]['referential']['uuid']
                    ?? $data['measures'][$measureUuid]['referential'];

                $referential = $this->importCacheHelper->getItemFromArrayCache('referentials', $referentialUuid)
                    ?: $this->referentialTable->findByUuidAndAnr($referentialUuid, $anr);

                /* For backward compatibility. */
                if ($referential === null
                    && isset($data['measures'][$measureUuid]['referential'][$labelKey])
                ) {
                    $referential = (new Referential())
                        ->setAnr($anr)
                        ->setUuid($referentialUuid)
                        ->setLabels([$labelKey => $data['measures'][$measureUuid]['referential'][$labelKey]])
                        ->setCreator($this->connectedUser->getEmail());

                    $this->referentialTable->save($referential, false);

                    $this->importCacheHelper->addItemToArrayCache('referentials', $referential, $referentialUuid);
                }

                /* For backward compatibility. */
                if ($referential === null) {
                    continue;
                }

                $soaCategory = $this->soaCategoryService->getOrCreateSoaCategory(
                    $this->importCacheHelper,
                    $anr,
                    $referential,
                    $data['measures'][$measureUuid]['category'][$labelKey] ?? ''
                );

                $measure = (new Measure())
                    ->setAnr($anr)
                    ->setUuid($measureUuid)
                    ->setCategory($soaCategory)
                    ->setReferential($referential)
                    ->setCode($data['measures'][$measureUuid]['code'])
                    ->setLabels($data['measures'][$measureUuid])
                    ->setCreator($this->connectedUser->getEmail());

                $this->importCacheHelper->addItemToArrayCache('measures', $measure, $measure->getUuid());
            }

            $measure->addAmv($amv);

            $this->measureTable->save($measure, false);
        }
    }
}
