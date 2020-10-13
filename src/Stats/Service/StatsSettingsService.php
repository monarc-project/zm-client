<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Stats\Service;

use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Monarc\FrontOffice\Model\Entity\Setting;
use Monarc\FrontOffice\Model\Table\AnrTable;
use Monarc\FrontOffice\Model\Table\SettingTable;
use Monarc\FrontOffice\Stats\Provider\StatsApiProvider;
use Throwable;

class StatsSettingsService
{
    /** @var AnrTable */
    private $anrTable;

    /** @var SettingTable */
    private $settingTable;

    /** @var StatsApiProvider */
    private $statsApiProvider;

    public function __construct(AnrTable $anrTable, SettingTable $settingTable, StatsApiProvider $statsApiProvider)
    {
        $this->anrTable = $anrTable;
        $this->settingTable = $settingTable;
        $this->statsApiProvider = $statsApiProvider;
    }

    public function isStatsAvailable(): bool
    {
        $setting = $this->settingTable->findByName(Setting::SETTINGS_STATS);
        if (
            !isset(
                $setting->getValue()[Setting::SETTING_STATS_IS_SHARING_ENABLED],
                $setting->getValue()[Setting::SETTING_STATS_API_KEY]
            )
            || empty($setting->getValue()[Setting::SETTING_STATS_IS_SHARING_ENABLED])
            || empty($setting->getValue()[Setting::SETTING_STATS_API_KEY])
        ) {
            return false;
        }

        try {
            $client = $this->statsApiProvider->getClient();
        } catch (Throwable $e) {
            return false;
        }

        return $client['token'] === $setting->getValue()[Setting::SETTING_STATS_API_KEY];
    }

    public function getAnrsSettings(): array
    {
        $anrsSettings = [];
        foreach ($this->anrTable->findAllExcludeSnapshots() as $anr) {
            $anrsSettings[] = [
                'anrId' => $anr->getId(),
                'anrName' => $anr->getLabel(),
                'isVisible' => $anr->isVisibleOnDashboard(),
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
        $anrSettings = array_column($anrSettings, 'isVisible', 'anrId');

        foreach ($this->anrTable->findByIds(array_keys($anrSettings)) as $anr) {
            $anr->setIsVisibleOnDashboard((int)$anrSettings[$anr->getId()]);
            $updatedAnrsSettings[] = [
                'anrId' => $anr->getId(),
                'anrName' => $anr->getLabel(),
                'isVisible' => $anr->isVisibleOnDashboard(),
            ];

            $this->anrTable->saveEntity($anr, false);
        }
        $this->anrTable->getDb()->flush();

        return $updatedAnrsSettings;
    }
}
