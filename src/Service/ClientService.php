<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Entity\UserSuperClass;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Entity\Client;
use Monarc\FrontOffice\Table\ClientTable;

class ClientService
{
    private UserSuperClass $connectedUser;

    public function __construct(private ClientTable $clientTable, ConnectedUserService $connectedUserService)
    {
        $this->connectedUser = $connectedUserService->getConnectedUser();
    }

    public function getAll(): array
    {
        $clients = [];
        foreach ($this->clientTable->findAll() as $client) {
            $clients[] = [
                'id' => $client->getId(),
                'name' => $client->getName(),
                'contact_email' => $client->getContactEmail(),
            ];
        }

        return [
            'count' => \count($clients),
            'clients' => $clients,
        ];
    }

    public function create(array $data): void
    {
        $client = (new Client())
            ->setName($data['name'])
            ->setContactEmail($data['contact_email'])
            ->setCreator($this->connectedUser->getEmail());

        $this->clientTable->save($client);
    }

    public function patch(int $id, array $data): void
    {
        /** @var Client $client */
        $client = $this->clientTable->findById($id);

        $client->setName($data['name'])
            ->setContactEmail($data['contact_email'])
            ->setUpdater($this->connectedUser->getEmail());

        $this->clientTable->save($client);
    }
}
