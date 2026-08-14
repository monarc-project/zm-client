<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2026 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Import\Processor;

use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Entity;
use Monarc\FrontOffice\Import\Helper\ImportCacheHelper;
use Monarc\FrontOffice\Table\RiskSourceTable;

class RiskSourceImportProcessor
{
    private Entity\User $connectedUser;

    public function __construct(
        private RiskSourceTable $riskSourceTable,
        private ImportCacheHelper $importCacheHelper,
        ConnectedUserService $connectedUserService
    ) {
        /** @var Entity\User $connectedUser */
        $connectedUser = $connectedUserService->getConnectedUser();
        $this->connectedUser = $connectedUser;
    }

    public function processRiskSourcesData(Entity\Anr $anr, array $riskSourcesData): void
    {
        foreach ($riskSourcesData as $riskSourceData) {
            $this->processRiskSourceData($anr, $riskSourceData);
        }
    }

    public function processRiskSourceData(Entity\Anr $anr, ?array $riskSourceData): ?Entity\RiskSource
    {
        $label = trim((string)($riskSourceData['label'] ?? ''));
        if ($label === '') {
            return null;
        }

        $normalizedLabel = mb_strtolower($label);
        $riskSource = $this->getRiskSourceFromCache($anr, $normalizedLabel);
        if ($riskSource === null) {
            $riskSource = (new Entity\RiskSource())
                ->setAnr($anr)
                ->setLabel($label)
                ->setIsDefault((bool)($riskSourceData['isDefault'] ?? false))
                ->setIsActive((bool)($riskSourceData['isActive'] ?? true))
                ->setCreator($this->connectedUser->getEmail());

            $this->riskSourceTable->save($riskSource, false);
            $this->importCacheHelper->addItemToArrayCache('risk_sources_by_label', $riskSource, $normalizedLabel);

            return $riskSource;
        }

        $isUpdated = false;
        if (array_key_exists('isDefault', $riskSourceData)
            && $riskSource->isDefault() !== (bool)$riskSourceData['isDefault']
        ) {
            $riskSource->setIsDefault((bool)$riskSourceData['isDefault']);
            $isUpdated = true;
        }
        if (array_key_exists('isActive', $riskSourceData)
            && $riskSource->isActive() !== (bool)$riskSourceData['isActive']
        ) {
            $riskSource->setIsActive((bool)$riskSourceData['isActive']);
            $isUpdated = true;
        }

        if ($isUpdated) {
            $this->riskSourceTable->save($riskSource, false);
        }

        return $riskSource;
    }

    private function getRiskSourceFromCache(Entity\Anr $anr, string $normalizedLabel): ?Entity\RiskSource
    {
        if (!$this->importCacheHelper->isCacheKeySet('is_risk_sources_cache_loaded')) {
            $this->importCacheHelper->setArrayCacheValue('is_risk_sources_cache_loaded', true);
            /** @var Entity\RiskSource $riskSource */
            foreach ($this->riskSourceTable->findByAnr($anr) as $riskSource) {
                $this->importCacheHelper->addItemToArrayCache(
                    'risk_sources_by_label',
                    $riskSource,
                    mb_strtolower($riskSource->getLabel())
                );
            }
        }

        return $this->importCacheHelper->getItemFromArrayCache('risk_sources_by_label', $normalizedLabel);
    }
}
