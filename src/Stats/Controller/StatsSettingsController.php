<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Stats\Controller;

use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\View\Model\JsonModel;
use Monarc\FrontOffice\Stats\Service\StatsSettingsService;

class StatsSettingsController extends AbstractRestfulController
{
    /** @var StatsSettingsService */
    private $statsSettingsService;

    public function __construct(StatsSettingsService $statsSettingsService)
    {
        $this->statsSettingsService = $statsSettingsService;
    }

    public function validateStatsAvailabilityAction(): JsonModel
    {
        if ($this->statsSettingsService->isStatsAvailable()) {
            $this->getResponse()->setStatusCode(204);
        } else {
            $this->getResponse()->setStatusCode(404);
        }

        return new JsonModel([]);
    }

    public function patchList($data): JsonModel
    {
        $updatedList = $this->statsSettingsService->updateAnrsSettings($data);

        return new JsonModel($updatedList);
    }

    public function getList(): JsonModel
    {
        return new JsonModel($this->statsSettingsService->getAnrsSettings());
    }
}
