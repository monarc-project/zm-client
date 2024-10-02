<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Table;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityNotFoundException;
use Monarc\Core\Table\AbstractTable;
use Monarc\FrontOffice\Entity\SystemMessage;
use Monarc\FrontOffice\Entity\User;

class SystemMessageTable extends AbstractTable
{
    public function __construct(EntityManager $entityManager, string $entityName = SystemMessage::class)
    {
        parent::__construct($entityManager, $entityName);
    }

    /**
     * @return SystemMessage[]
     */
    public function findAllActiveByUser(User $user): array
    {
        return $this->getRepository()->createQueryBuilder('sm')
            ->where('sm.user = :user')
            ->andWhere('sm.status = ' . SystemMessage::STATUS_ACTIVE)
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();
    }

    public function findByIdAndUser(int $id, User $user): SystemMessage
    {
        $systemMessage = $this->getRepository()->createQueryBuilder('sm')
            ->where('sm.id = :id')
            ->andWhere('sm.user = :user')
            ->setParameter('id', $id)
            ->setParameter('user', $user)
            ->getQuery()
            ->getOneOrNullResult();
        if ($systemMessage === null) {
            throw EntityNotFoundException::fromClassNameAndIdentifier(SystemMessage::class, [$id]);
        }

        return $systemMessage;
    }
}
