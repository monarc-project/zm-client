<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Controller;

use Monarc\Core\Controller\Handler\AbstractRestfulControllerRequestHandler;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Service\AnrHistoryService;

class ApiAnrHistoryController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;

    public function __construct(private AnrHistoryService $anrHistoryService)
    {
    }

    public function getList()
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');
        $targetType = (int)$this->params()->fromQuery('targetType', 0);
        $targetId = (int)$this->params()->fromQuery('targetId', 0);
        $changeType = (int)$this->params()->fromQuery('changeType', 0);

        $entries = [];
        if ($targetType > 0) {
            $historyEntries = $targetId > 0
                ? $this->anrHistoryService->getTargetHistory(
                    $anr,
                    $targetType,
                    $targetId,
                    $changeType > 0 ? $changeType : null
                )
                : $this->anrHistoryService->getHistory(
                    $anr,
                    [$targetType],
                    $changeType > 0 ? $changeType : null
                );
            if ($targetId <= 0) {
                $historyEntries = array_reverse($historyEntries);
            }

            foreach ($historyEntries as $entry) {
                $entries[] = $this->anrHistoryService->prepareEntryData($entry);
            }
        }

        return $this->getPreparedJsonResponse([
            'count' => count($entries),
            'history' => $entries,
        ]);
    }
}
