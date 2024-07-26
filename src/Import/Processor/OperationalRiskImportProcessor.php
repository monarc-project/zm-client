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

class OperationalRiskImportProcessor
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
        foreach ($operationalRisksData as $operationalRiskData) {
            $this->processOperationalRiskData($anr, $operationalRiskData);
        }
    }

    public function processOperationalRiskData(Entity\Anr $anr, array $operationalRiskData): Entity\RolfRisk
    {
        $operationalRisk = $this->getRolfRiskFromCache($anr, $operationalRiskData['code']);
        if ($operationalRisk === null) {
            $operationalRisk = $this->anrRolfRiskService->create($anr, [
                'code' => $operationalRiskData['code'],
                'label' . $anr->getLanguage() =>
                    $operationalRiskData['label'] ?? $operationalRiskData['label' . $anr->getLanguage()],
                'description' . $anr->getLanguage() =>
                    $operationalRiskData['label'] ?? $operationalRiskData['description' . $anr->getLanguage()],
            ], false);
            $this->importCacheHelper->addItemToArrayCache(
                'rolf_risks_by_code',
                $operationalRisk,
                $operationalRisk->getCode()
            );
        }

        $saveOperationalRisk = false;
        foreach ($operationalRiskData['measures'] as $measureData) {
            $measure = $this->referentialImportProcessor->getMeasureFromCache($anr, $measureData['uuid']);
            if ($measure === null && !empty($measureData['referential'])) {
                $referential = $this->referentialImportProcessor->processReferentialData(
                    $anr,
                    $measureData['referential']
                );
                $measure = $this->referentialImportProcessor->processMeasureData($anr, $referential, $measureData);
            }
            if ($measure !== null) {
                $operationalRisk->addMeasure($measure);
                $saveOperationalRisk = true;
            }
        }
        foreach ($operationalRiskData['rolfTags'] ?? [] as $rolfTagData) {
            $rolfTag = $this->rolfTagImportProcessor->processRolfTagData($anr, $rolfTagData);
            if (!$operationalRisk->hasRolfTag($rolfTag)) {
                $saveOperationalRisk = true;
            }
            $operationalRisk->addTag($rolfTag);
        }

        if ($saveOperationalRisk) {
            $this->rolfRiskTable->save($operationalRisk, false);
            $this->importCacheHelper
                ->addItemToArrayCache('rolf_risks_by_code', $operationalRisk, $operationalRisk->getCode());
        }

        return $operationalRisk;
    }

    private function getRolfRiskFromCache(Entity\Anr $anr, string $code): ?Entity\RolfRisk
    {
        if (!$this->importCacheHelper->isCacheKeySet('is_rolf_risks_loaded')) {
            $this->importCacheHelper->setArrayCacheValue('is_rolf_risks_loaded', true);
            /** @var Entity\RolfRisk $rolfRisk */
            foreach ($this->rolfRiskTable->findByAnr($anr) as $rolfRisk) {
                $this->importCacheHelper->addItemToArrayCache('rolf_risks_by_code', $rolfRisk, $rolfRisk->getCode());
            }
        }

        return $this->importCacheHelper->getItemFromArrayCache('rolf_risks_by_code', $code);
    }
}
