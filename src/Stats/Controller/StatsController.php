<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Stats\Controller;

use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\View\Model\JsonModel;
use Monarc\Core\Exception\Exception;
use Monarc\FrontOffice\Exception\AccessForbiddenException;
use Monarc\FrontOffice\Stats\Service\StatsAnrService;
use Monarc\FrontOffice\Stats\Validator\GetStatsQueryParamsValidator;
use Monarc\FrontOffice\Stats\Validator\GetProcessedStatsQueryParamsValidator;

class StatsController extends AbstractRestfulController
{
    public function __construct(
        private GetStatsQueryParamsValidator $getStatsQueryParamsValidator,
        private GetProcessedStatsQueryParamsValidator $getProcessedStatsQueryParamsValidator,
        private StatsAnrService $statsAnrService
    ) {
    }

    public function getList(): JsonModel
    {
        if (!$this->getStatsQueryParamsValidator->isValid($this->params()->fromQuery())) {
            throw new Exception(
                'Query params validation errors: [ '
                . json_encode($this->getStatsQueryParamsValidator->getErrorMessages(), JSON_THROW_ON_ERROR)
                . ']',
                400
            );
        }

        try {
            $stats = $this->statsAnrService->getStats($this->getStatsQueryParamsValidator->getValidData());
        } catch (AccessForbiddenException) {
            $stats = [];
            $this->getResponse()->setStatusCode(403);
        }

        return new JsonModel([
            'data' => $stats,
        ]);
    }

    public function getProcessedListAction(): JsonModel
    {
        if (!$this->getProcessedStatsQueryParamsValidator->isValid($this->params()->fromQuery())) {
            throw new Exception(
                'Query params validation errors: [ '
                . json_encode($this->getProcessedStatsQueryParamsValidator->getErrorMessages(), JSON_THROW_ON_ERROR)
                . ']',
                400
            );
        }

        try {
            $stats = $this->statsAnrService->getProcessedStats(
                $this->getProcessedStatsQueryParamsValidator->getValidData()
            );
        } catch (AccessForbiddenException) {
            $stats = [];
            $this->getResponse()->setStatusCode(403);
        }

        return new JsonModel([
            'data' => $stats,
        ]);
    }

    public function validateStatsAvailabilityAction(): JsonModel
    {
        return new JsonModel(['isStatsAvailable' => $this->statsAnrService->isStatsAvailable()]);
    }
}
