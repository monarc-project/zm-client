<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Model\Table;

use Doctrine\ORM\EntityNotFoundException;
use Monarc\Core\Model\Entity\AnrSuperClass;
use Monarc\Core\Model\Entity\ThreatSuperClass;
use Monarc\Core\Model\Table\AbstractEntityTable;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Model\DbCli;
use Monarc\FrontOffice\Model\Entity\Threat;

/**
 * Class ThreatTable
 * @package Monarc\FrontOffice\Model\Table
 */
class ThreatTable extends AbstractEntityTable
{
    public function __construct(DbCli $dbService, ConnectedUserService $connectedUserService)
    {
        parent::__construct($dbService, Threat::class, $connectedUserService);
    }

    /**
     * @param $anrId
     * @return bool
     */
    public function started($anrId)
    {
        $qb = $this->getRepository()->createQueryBuilder('t');
        $res = $qb->select('COUNT(t.uuid)')
            ->where('t.anr = :anrid')
            ->setParameter(':anrid', $anrId)
            ->andWhere('t.qualification != -1')->getQuery()->getSingleScalarResult();
        return $res > 0;
    }

    /**
     * @throws EntityNotFoundException
     */
    public function findByAnrAndUuid(AnrSuperClass $anr, string $uuid): ThreatSuperClass
    {
        $threat = $this->getRepository()
            ->createQueryBuilder('t')
            ->where('t.anr = :anr')
            ->andWhere('t.uuid = :uuid')
            ->setParameter(':anr', $anr)
            ->setParameter(':uuid', $uuid)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($threat === null) {
            throw new EntityNotFoundException(
                sprintf('Threat with anr ID "%d" and uuid "%s" has not been found.', $anr->getId(), $uuid)
            );
        }

        return $threat;
    }
}
