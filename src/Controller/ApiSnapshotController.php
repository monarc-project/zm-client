<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2023 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\Core\Controller\Handler\AbstractRestfulControllerRequestHandler;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\FrontOffice\Model\Entity\Anr;
use Monarc\FrontOffice\Service\SnapshotService;

class ApiSnapshotController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;

    private SnapshotService $snapshotService;

    public function __construct(SnapshotService $snapshotService)
    {
        $this->snapshotService = $snapshotService;
    }

    public function getList()
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        $filterAnd = ['anrReference' => $anrId];

        if (!is_null($status) && $status != 'all') {
            $filterAnd['status'] = $status;
        }

        $service = $this->getService();

        // TODO: add required anr data to the getList response.
        $entities = $service->getList($page, $limit, $order, $filter, $filterAnd);
        foreach ($entities as $key => $entity) {
            $this->formatDependencies($entities[$key], ['anr']);
        }

        return $this->getPreparedJsonResponse([
            'count' => $service->getFilteredCount($filter, $filterAnd),
            'snapshots' => $entities
        ]);
    }

    /**
     * @param array $data
     */
    public function create($data)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        $snapshot = $this->snapshotService->create($anr, $data);

        return $this->getSuccessfulJsonResponse(['id' => $snapshot->getId()]);
    }

    public function delete($id)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        $this->snapshotService->delete($anr, (int)$id);

        return $this->getSuccessfulJsonResponse();
    }
}
