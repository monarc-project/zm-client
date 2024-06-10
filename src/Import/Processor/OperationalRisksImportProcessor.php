<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Import\Processor;

use Monarc\FrontOffice\Entity;
use Monarc\FrontOffice\Import\Helper\ImportCacheHelper;
use Monarc\FrontOffice\Service\AnrRolfRiskService;
use Monarc\FrontOffice\Table\RolfRiskTable;

class OperationalRisksImportProcessor
{
    public function __construct(
        private RolfRiskTable $rolfRiskTable,
        private ImportCacheHelper $importCacheHelper,
        private AnrRolfRiskService $anrRolfRiskService,
        private ReferentialImportProcessor $referentialImportProcessor,
        private RolfTagImportProcessor $rolfTagImportProcessor
    ) {
    }

    public function processOperationalRisksData(Entity\Anr $anr, array $operationalRisksData): void
    {
        $this->prepareRolfRisksCache($anr);
        foreach ($operationalRisksData as $operationalRiskData) {
            $this->processOperationalRiskData($anr, $operationalRiskData);
        }
    }

    public function processOperationalRiskData(Entity\Anr $anr, array $operationalRiskData): Entity\RolfRisk
    {
        $operationalRisk = $this->getRolfRiskFromCache($operationalRiskData['code']);
        if ($operationalRisk !== null) {
            return $operationalRisk;
        }

        $operationalRisk = $this->anrRolfRiskService->create($anr, [
            'code' => $operationalRiskData['code'],
            'label' . $anr->getLanguage() => $operationalRiskData['label']
                ?? $operationalRiskData['label' . $anr->getLanguage()],
            'description' . $anr->getLanguage() => $operationalRiskData['label']
                ?? $operationalRiskData['description' . $anr->getLanguage()],
        ], false);
        $this->importCacheHelper->addItemToArrayCache(
            'rolf_risks_by_code',
            $operationalRisk,
            $operationalRisk->getCode()
        );
        foreach ($operationalRiskData['measures'] as $measureData) {
            $measureUuid = $measureData['uuid'] ?? $measureData;
            $measure = $this->referentialImportProcessor->getMeasureFromCache($measureUuid);
            if ($measure !== null) {
                $operationalRisk->addMeasure($measure);
            }
        }
        foreach ($operationalRiskData['rolfTags'] as $rolfTagData) {
            $rolfTag = $this->rolfTagImportProcessor->getRolfTagFromCache($rolfTagData['code']);
            if ($rolfTag !== null) {
                $operationalRisk->addTag($rolfTag);
            }
        }
        $this->rolfRiskTable->save($operationalRisk, false);

        return $operationalRisk;
    }

    public function getRolfRiskFromCache(string $code): ?Entity\RolfRisk
    {
        return $this->importCacheHelper->getItemFromArrayCache('rolf_risks_by_code', $code);
    }

    public function prepareRolfRisksCache(Entity\Anr $anr): void
    {
        if (!$this->importCacheHelper->isCacheKeySet('rolf_risks_by_code')) {
            /** @var Entity\RolfRisk $rolfRisk */
            foreach ($this->rolfRiskTable->findByAnr($anr) as $rolfRisk) {
                $this->importCacheHelper->addItemToArrayCache('rolf_risks_by_code', $rolfRisk, $rolfRisk->getCode());
            }
        }
    }
}
