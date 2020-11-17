<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\FrontOffice\Model\Table\ClientTable;

class ClientService
{
    /** @var ClientTable */
    private $clientTable;

    public function __construct(ClientTable $clientTable)
    {
        $this->clientTable = $clientTable;
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

    public function patch(int $id, array $data): void
    {
        $client = $this->clientTable->findById($id);

        $client->setName($data['name']);
        $client->setContactEmail($data['contact_email']);

        $this->clientTable->saveEntity($client);
    }
}
