<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Model\Table;

use Monarc\Core\Model\Table\AbstractEntityTable;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Model\DbCli;
use Monarc\FrontOffice\Model\Entity\Anr;
use Monarc\FrontOffice\Model\Entity\Recommandation;

/**
 * Class RecommandationTable
 * @package Monarc\FrontOffice\Model\Table
 */
class RecommandationTable extends AbstractEntityTable
{
    public function __construct(DbCli $dbService, ConnectedUserService $connectedUserService)
    {
        parent::__construct($dbService, Recommandation::class, $connectedUserService);
    }

    public function getMaxPositionByAnr(Anr $anr): int
    {
        return (int)$this->getRepository()
            ->createQueryBuilder('r')
            ->select('MAX(r.position)')
            ->where('r.anr = :anr')
            ->setParameter('anr', $anr)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function saveEntity(Recommandation $recommendation, bool $flush = true): void
    {
        $em = $this->getDb()->getEntityManager();
        $em->persist($recommendation);
        if ($flush) {
            $em->flush();
        }
    }
}
