<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Stats\Service;

use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Monarc\FrontOffice\Table\AnrTable;
use Monarc\FrontOffice\Stats\Exception\StatsGetClientException;
use Monarc\FrontOffice\Stats\Exception\StatsUpdateClientException;
use Monarc\FrontOffice\Stats\Provider\StatsApiProvider;

class StatsSettingsService
{
    /** @var AnrTable */
    private $anrTable;

    /** @var StatsApiProvider */
    private $statsApiProvider;

    public function __construct(AnrTable $anrTable, StatsApiProvider $statsApiProvider)
    {
        $this->anrTable = $anrTable;
        $this->statsApiProvider = $statsApiProvider;
    }

    public function getAnrsSettings(): array
    {
        $anrsSettings = [];
        foreach ($this->anrTable->findAllExcludeSnapshots() as $anr) {
            $anrsSettings[] = [
                'anrId' => $anr->getId(),
                'uuid' => $anr->getUuid(),
                'anrName' => $anr->getLabel(),
                'isVisible' => $anr->isVisibleOnDashboard(),
                'isStatsCollected' => $anr->isStatsCollected(),
            ];
        }

        return $anrsSettings;
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function updateAnrsSettings(array $anrSettings): array
    {
        if (empty($anrSettings)) {
            return [];
        }

        $updatedAnrsSettings = [];
        $anrSettings = array_column($anrSettings, null, 'anrId');

        foreach ($this->anrTable->findByIds(array_keys($anrSettings)) as $anr) {
            $anr->setIsVisibleOnDashboard((int)$anrSettings[$anr->getId()]['isVisible'])
                ->setIsStatsCollected((int)$anrSettings[$anr->getId()]['isStatsCollected']);
            $updatedAnrsSettings[] = [
                'anrId' => $anr->getId(),
                'anrName' => $anr->getLabel(),
                'isVisible' => $anr->isVisibleOnDashboard(),
                'isStatsCollected' => $anr->isStatsCollected(),
            ];

            $this->anrTable->save($anr, false);
        }
        $this->anrTable->getDb()->flush();

        return $updatedAnrsSettings;
    }

    /**
     * @throws StatsGetClientException
     */
    public function getGeneralSettings(): array
    {
        try {
            $client = $this->statsApiProvider->getClient();
        } catch (\Throwable) {
        }

        return [
            'is_sharing_enabled' => $client['is_sharing_enabled'] ?? false,
        ];
    }

    /**
     * @throws StatsUpdateClientException
     */
    public function updateGeneralSettings(array $data): void
    {
        if (!isset($data['is_sharing_enabled'])) {
            throw new StatsUpdateClientException('The option `is_sharing_enabled` is mandatory.');
        }

        try {
            $this->statsApiProvider->updateClient(['is_sharing_enabled' => (bool)$data['is_sharing_enabled']]);
        } catch (\Throwable) {
        }
    }
}
