<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Stats\Controller;

use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\View\Model\JsonModel;
use Monarc\FrontOffice\Stats\Service\StatsSettingsService;

class StatsAnrsSettingsController extends AbstractRestfulController
{
    /** @var StatsSettingsService */
    private $statsSettingsService;

    public function __construct(StatsSettingsService $statsSettingsService)
    {
        $this->statsSettingsService = $statsSettingsService;
    }

    public function patchList($data): JsonModel
    {
        $this->statsSettingsService->updateAnrsSettings($data);

        return new JsonModel([
            'status' => 'ok'
        ]);
    }

    public function getList(): JsonModel
    {
        return new JsonModel([
            'data' => $this->statsSettingsService->getAnrsSettings()
        ]);
    }
}
