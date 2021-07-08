<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2021 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Laminas\Http\Response;
use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\View\Model\JsonModel;
use Monarc\FrontOffice\Service\AnrRiskOwnersService;

/**
 * Api ANR Risk Owners Controller
 *
 * Class ApiAnrRiskOwnersController
 * @package Monarc\FrontOffice\Controller
 */
class ApiAnrRiskOwnersController extends AbstractRestfulController
{
    /** @var AnrRiskOwnersService */
    private $anrRiskOwnersService;

    public function __construct(AnrRiskOwnersService $anrRiskOwnersService)
    {
        $this->anrRiskOwnersService = $anrRiskOwnersService;
    }

    public function getList()
    {
        $anrId = (int)$this->params()->fromRoute('anrid');
        $params = $this->prepareParams();
        
        $owners = $this->anrRiskOwnersService->getOwners($anrId, null, $params);
        
        return new JsonModel([
            'count' => \count($owners),
            'owners' => $params['limit'] > 0
                ? \array_slice($owners, ($params['page'] - 1) * $params['limit'], $params['limit'])
                : $owners,
        ]);
    }
    
    protected function prepareParams(): array
    {
        $params = $this->params();

        return [
            'order' => $params->fromQuery('order', 'maxRisk'),
            'order_direction' => $params->fromQuery('order_direction', 'desc'),
            'page' => $params->fromQuery('page', 1),
            'limit' => $params->fromQuery('limit', 50)
        ];
    }
}
