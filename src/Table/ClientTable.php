<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2023 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Table;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityNotFoundException;
use Monarc\Core\Table\AbstractTable;
use Monarc\FrontOffice\Model\Entity\Client;

class ClientTable extends AbstractTable
{
    public function __construct(EntityManager $entityManager, string $entityName = Client::class)
    {
        parent::__construct($entityManager, $entityName);
    }

    /**
     * @return Client[]
     */
    public function findAll(): array
    {
        return $this->getRepository()->findAll();
    }

    public function findFirstClient(): Client
    {
        $client = $this->getRepository()->createQueryBuilder('c')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
        if ($client === null) {
            throw new EntityNotFoundException('There are no clients exist.');
        }

        return $client;
    }
}
