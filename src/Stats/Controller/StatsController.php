<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Stats\Controller;

use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\View\Model\JsonModel;
use Monarc\Core\Exception\Exception;
use Monarc\FrontOffice\Exception\AccessForbiddenException;
use Monarc\FrontOffice\Exception\UserNotAuthorizedException;
use Monarc\FrontOffice\Stats\Service\StatsAnrService;
use Monarc\FrontOffice\Stats\Validator\GetStatsQueryParamsValidator;
use Monarc\FrontOffice\Stats\Validator\GetProcessedStatsQueryParamsValidator;

class StatsController extends AbstractRestfulController
{
    /** @var GetStatsQueryParamsValidator */
    private $getStatsQueryParamsValidator;

    /** @var GetProcessedStatsQueryParamsValidator */
    private $getProcessedStatsQueryParamsValidator;

    /** @var StatsAnrService */
    private $statsAnrService;

    public function __construct(
        GetStatsQueryParamsValidator $getStatsQueryParamsValidator,
        GetProcessedStatsQueryParamsValidator $getProcessedStatsQueryParamsValidator,
        StatsAnrService $statsAnrService
    ) {
        $this->getStatsQueryParamsValidator = $getStatsQueryParamsValidator;
        $this->getProcessedStatsQueryParamsValidator = $getProcessedStatsQueryParamsValidator;
        $this->statsAnrService = $statsAnrService;
    }

    public function getList(): JsonModel
    {
        if (!$this->getStatsQueryParamsValidator->isValid($this->params()->fromQuery())) {
            throw new Exception(
                'Query params validation errors: [ '
                . json_encode($this->getStatsQueryParamsValidator->getErrorMessages()),
                400
            );
        }

        try {
            $stats = $this->statsAnrService->getStats($this->getStatsQueryParamsValidator->getValidData());
        } catch (UserNotAuthorizedException | AccessForbiddenException $e) {
            $stats = [];
            $this->getResponse()->setStatusCode(401);
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
                . json_encode($this->getProcessedStatsQueryParamsValidator->getErrorMessages()),
                400
            );
        }

        try {
            $stats = $this->statsAnrService->getProcessedStats($this->getProcessedStatsQueryParamsValidator->getValidData());
        } catch (UserNotAuthorizedException | AccessForbiddenException $e) {
            $stats = [];
            $this->getResponse()->setStatusCode(401);
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
