<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Laminas\Mvc\Controller\AbstractRestfulController;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\FrontOffice\Service\ClientService;

class ApiClientsController extends AbstractRestfulController
{
    use ControllerRequestResponseHandlerTrait;

    public function __construct(private ClientService $clientService)
    {
    }

    public function getList()
    {
        return $this->getPreparedJsonResponse($this->clientService->getAll());
    }

    public function create($data)
    {
        $this->clientService->create($data);

        return $this->getSuccessfulJsonResponse();
    }

    public function patch($id, $data)
    {
        $this->clientService->patch((int)$id, $data);

        return $this->getSuccessfulJsonResponse();
    }
}
