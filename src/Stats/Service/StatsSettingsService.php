<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Stats\Service;

use Doctrine\ORM\EntityNotFoundException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Monarc\FrontOffice\Model\Entity\Setting;
use Monarc\FrontOffice\Model\Table\AnrTable;
use Monarc\FrontOffice\Model\Table\SettingTable;

class StatsSettingsService
{
    /** @var AnrTable */
    private $anrTable;

    /** @var SettingTable */
    private $settingTable;

    public function __construct(AnrTable $anrTable, SettingTable $settingTable)
    {
        $this->anrTable = $anrTable;
        $this->settingTable = $settingTable;
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

    /**
     * @throws EntityNotFoundException
     */
    public function getGeneralSettings(): array
    {
        $setting = $this->settingTable->findByName(Setting::SETTINGS_STATS);

        return $setting->getValue();
    }

    /**
     * @throws EntityNotFoundException
     */
    public function updateGeneralSettings(array $data)
    {
        $setting = $this->settingTable->findByName(Setting::SETTINGS_STATS);
        $settingValues = $setting->getValue();
        foreach (array_keys($setting->getValue()) as $name) {
            if (isset($data[$name])) {
                $settingValues[$name] = $data[$name];
            }
        }

        $setting->setValue($settingValues);
    }
}
