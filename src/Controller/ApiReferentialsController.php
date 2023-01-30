<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2022 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\View\Model\JsonModel;
use Monarc\FrontOffice\Service\AnrReferentialService;

class ApiReferentialsController extends AbstractRestfulController
{
    private AnrReferentialService $anrReferentialService;

    public function __construct(AnrReferentialService $anrReferentialService)
    {
        $this->anrReferentialService = $anrReferentialService;
    }

    /**
     * Is used in analysis creation.
     */
    public function getList()
    {
        $filter = $this->params()->fromQuery('filter');
        $order = $this->params()->fromQuery('order');

        $referentials = $this->anrReferentialService->getCommonReferentials($filter, $order);

        return new JsonModel([
            'count' => \count($referentials),
            'referentials' => $referentials,
        ]);
    }
}
