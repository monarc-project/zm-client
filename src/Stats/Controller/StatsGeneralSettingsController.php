<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Stats\Controller;

use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\View\Model\JsonModel;
use Monarc\FrontOffice\Stats\Service\StatsSettingsService;

class StatsGeneralSettingsController extends AbstractRestfulController
{
    public function __construct(private StatsSettingsService $statsSettingsService)
    {
    }

    public function patchList($data): JsonModel
    {
        $this->statsSettingsService->updateGeneralSettings($data);

        return new JsonModel([
            'status' => 'ok'
        ]);
    }

    public function getList(): JsonModel
    {
        return new JsonModel([
            'data' => $this->statsSettingsService->getGeneralSettings()
        ]);
    }
}
