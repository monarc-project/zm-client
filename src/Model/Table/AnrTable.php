<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Model\Table;

use Doctrine\ORM\EntityNotFoundException;
use Doctrine\ORM\Query\Expr;
use Monarc\FrontOffice\Model\DbCli;
use Monarc\Core\Model\Table\AbstractEntityTable;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Model\Entity\Anr;
use Monarc\FrontOffice\Model\Entity\Snapshot;
use Monarc\FrontOffice\Model\Entity\UserAnr;

/**
 * Class AnrTable
 * @package Monarc\FrontOffice\Model\Table
 */
class AnrTable extends AbstractEntityTable
{
    public function __construct(DbCli $dbService, ConnectedUserService $connectedUserService)
    {
        parent::__construct($dbService, Anr::class, $connectedUserService);
    }

    /**
     * @throws EntityNotFoundException
     */
    public function findById(int $anrId): Anr
    {
        /** @var Anr|null $anr */
        $anr = $this->getRepository()->find($anrId);
        if ($anr === null) {
            throw new EntityNotFoundException(sprintf('Anr with id "%d" was not found', $anrId));
        }

        return $anr;
    }

    public function fetchAnrsExcludeSnapshotsWithUserRights(int $userId): array
    {
        $excludedSnapshots = $this->getDb()
            ->getEntityManager()
            ->createQueryBuilder()
            ->select('anr.id')
            ->from(Snapshot::class, 's')
            ->join(Anr::class, 'anr', Expr\Join::WITH, 's.anr = anr');

        $queryBuilder = $this->getRepository()->createQueryBuilder('a');

        return $queryBuilder
            ->select('a.id, a.label1, a.label2, a.label3, a.label4, (CASE WHEN ua.rwd IS NULL THEN -1 ELSE ua.rwd END) AS rwd')
            ->leftJoin(UserAnr::class, 'ua', Expr\Join::WITH, 'ua.anr = a AND ua.user = :userId')
            ->where($queryBuilder->expr()->notIn('a.id', $excludedSnapshots->getDQL()))
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getArrayResult();
    }
}
