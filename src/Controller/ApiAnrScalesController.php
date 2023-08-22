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
use Monarc\FrontOffice\Service\AnrScaleService;

class ApiAnrScalesController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;

    private AnrScaleService $anrScaleService;

    public function __construct(AnrScaleService $anrScaleService)
    {
        $this->anrScaleService = $anrScaleService;
    }

    public function getList()
    {
        $page = $this->params()->fromQuery('page');
        $limit = $this->params()->fromQuery('limit');
        $order = $this->params()->fromQuery('order');
        $filter = $this->params()->fromQuery('filter');

        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        $filterAnd = ['anr' => $anr->getId()];

        [$entities, $canChange] = $this->anrScaleService->getList($page, $limit, $order, $filter, $filterAnd);
        // todo here we have only anr obj format...
//        if (count($this->dependencies)) {
//            foreach ($entities as $key => $entity) {
//                $this->formatDependencies($entities[$key], $this->dependencies);
//            }
//        }

        return $this->getPreparedJsonResponse([
            'count' => $this->anrScaleService->getFilteredCount($filter, $filterAnd),
            'scales' => $entities,
            'canChange' => $canChange,
        ]);
    }

    /**
     * @param array $data
     */
    public function update($id, $data)
    {
        // TODO: add the Validator of the $data.
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        $this->anrScaleService->update($anr, (int)$id, $data);

        return $this->getSuccessfulJsonResponse();
    }
}
