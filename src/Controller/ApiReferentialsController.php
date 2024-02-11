<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\View\Model\JsonModel;
use Monarc\FrontOffice\Service\AnrReferentialService;

class ApiReferentialsController extends AbstractRestfulController
{
    public function __construct(private AnrReferentialService $anrReferentialService)
    {
    }

    /**
     * Is used in analysis creation or edit to get the list of referential.
     */
    public function getList()
    {
        $filter = $this->params()->fromQuery('filter');
        $order = $this->params()->fromQuery('order');

        $referential = $this->anrReferentialService->getCommonReferentials($filter, $order);

        return new JsonModel([
            'count' => \count($referential),
            'referentials' => $referential,
        ]);
    }
}
