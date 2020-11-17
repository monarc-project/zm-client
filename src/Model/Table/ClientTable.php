<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Model\Table;

use Doctrine\ORM\EntityNotFoundException;
use Monarc\FrontOffice\Model\DbCli;
use Monarc\Core\Model\Table\AbstractEntityTable;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Model\Entity\Client;

/**
 * Class ClientTable
 * @package Monarc\FrontOffice\Model\Table
 */
class ClientTable extends AbstractEntityTable
{
    public function __construct(DbCli $dbService, ConnectedUserService $connectedUserService)
    {
        parent::__construct($dbService, Client::class, $connectedUserService);
    }

    /**
     * @return Client[]
     */
    public function findAll(): array
    {
        return $this->getRepository()->findAll();
    }

    /**
     * @throws EntityNotFoundException
     */
    public function findById(int $id): Client
    {
        /** @var Client|null $client */
        $client = $this->getRepository()->find($id);
        if ($client === null) {
            throw EntityNotFoundException::fromClassNameAndIdentifier(\get_class($this), [$id]);
        }

        return $client;
    }

    public function saveEntity(Client $client, bool $flushAll = true): void
    {
        $em = $this->getDb()->getEntityManager();
        $em->persist($client);
        if ($flushAll) {
            $em->flush();
        }
    }
}
