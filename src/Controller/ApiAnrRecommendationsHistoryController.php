<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2023 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\Core\Controller\Handler\AbstractRestfulControllerRequestHandler;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Service\AnrRecommendationHistoryService;

class ApiAnrRecommendationsHistoryController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;

    private AnrRecommendationHistoryService $recommendationHistoryService;

    public function __construct(AnrRecommendationHistoryService $recommendationHistoryService)
    {
        $this->recommendationHistoryService = $recommendationHistoryService;
    }

    public function getList()
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        $recommendationsHistoryList = $this->recommendationHistoryService->getList($anr);

        return $this->getPreparedJsonResponse([
            'count' => \count($recommendationsHistoryList),
            'recommendations-history' => $recommendationsHistoryList,
        ]);
    }
}
