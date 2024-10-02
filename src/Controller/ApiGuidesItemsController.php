<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\Core\Controller\AbstractController;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\Core\Service\GuideItemService;

class ApiGuidesItemsController extends AbstractController
{
    use ControllerRequestResponseHandlerTrait;

    public function __construct(GuideItemService $guideItemService)
    {
        parent::__construct($guideItemService);
    }

    public function getList()
    {
        $page = $this->params()->fromQuery('page');
        $limit = $this->params()->fromQuery('limit');
        $order = $this->params()->fromQuery('order');
        $filter = $this->params()->fromQuery('filter');
        $guide = $this->params()->fromQuery('guide');
        $filterAnd = [];
        if (!\is_null($guide)) {
            $filterAnd = ['guide' => (int)$guide];
        }

        $service = $this->getService();

        return $this->getPreparedJsonResponse([
            'count' => $service->getFilteredCount($filter, $filterAnd),
            'guides-items' => $service->getList($page, $limit, $order, $filter, $filterAnd),
        ]);
    }
}
