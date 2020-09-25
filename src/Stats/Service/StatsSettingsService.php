<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Stats\Service;

use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Monarc\FrontOffice\Model\Table\AnrTable;

class StatsSettingsService
{
    /** @var AnrTable */
    private $anrTable;

    public function __construct(AnrTable $anrTable)
    {
        $this->anrTable = $anrTable;
    }

    public function getAnrsSettings(): array
    {
        $anrsSettings = [];
        foreach ($this->anrTable->findAll() as $anr) {
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