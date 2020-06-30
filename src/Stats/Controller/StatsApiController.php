<?php

namespace Monarc\FrontOffice\Stats\Controller;

use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\View\Model\JsonModel;
use Monarc\Core\Exception\Exception;
use Monarc\FrontOffice\Exception\UserNotAuthorizedException;
use Monarc\FrontOffice\Stats\Service\StatsAnrService;
use Monarc\FrontOffice\Stats\Validator\GetStatsQueryParamsValidator;

class StatsApiController extends AbstractRestfulController
{
    /** @var GetStatsQueryParamsValidator */
    private $getStatsQueryParamsValidator;

    /** @var StatsAnrService */
    private $statsAnrService;

    public function __construct(
        GetStatsQueryParamsValidator $getStatsQueryParamsValidator,
        StatsAnrService $statsAnrService
    ) {
        $this->getStatsQueryParamsValidator = $getStatsQueryParamsValidator;
        $this->statsAnrService = $statsAnrService;
    }

    public function getList()
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
        } catch (UserNotAuthorizedException $e) {
            $stats = [];
            $this->getResponse()->setStatusCode(401);
        }

        return new JsonModel($stats);
    }
}
