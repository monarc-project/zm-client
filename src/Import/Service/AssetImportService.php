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
use Monarc\FrontOffice\Entity\Amv;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Entity\Asset;
use Monarc\FrontOffice\Entity\InstanceRisk;
use Monarc\FrontOffice\Import\Processor;
use Monarc\FrontOffice\Table;

// TODO: the service has to be removed. It's not used anywhere.
class AssetImportService
{
    private UserSuperClass $connectedUser;

    public function __construct(
        private Processor\AssetImportProcessor $assetImportProcessor,
        private Processor\ThreatImportProcessor $threatImportProcessor,
        private Processor\VulnerabilityImportProcessor $vulnerabilityImportProcessor,
        private Table\AmvTable $amvTable,
        private Table\InstanceRiskTable $instanceRiskTable,
        ConnectedUserService $connectedUserService
    ) {
        $this->connectedUser = $connectedUserService->getConnectedUser();
    }

    public function importFromArray(array $data, Anr $anr): ?Asset
    {
        if (!isset($data['type']) || $data['type'] !== 'asset') {
            return null;
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

    // TODO: use processors to create the objects.
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

                $threat = $this->threatImportProcessor->getThreatFromCache($anr, $amvData['threat']);
                $vulnerability = $this->vulnerabilityImportProcessor
                    ->getVulnerabilityFromCache($anr, $amvData['vulnerability']);
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

//            if (!empty($amvData['measures'])) {
//                $this->processMeasuresAndReferentialData($amvData['measures'], $anr, $amv);
//            }
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
}
