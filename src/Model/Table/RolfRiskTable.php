<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Model\Table;

use Monarc\Core\Model\Table\RolfRiskTable as CoreRolfRiskTable;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Model\DbCli;
use Monarc\FrontOffice\Model\Entity\Anr;
use Monarc\FrontOffice\Model\Entity\RolfRisk;

/**
 * Class RolfRiskTable
 * @package Monarc\FrontOffice\Model\Table
 */
class RolfRiskTable extends CoreRolfRiskTable
{
    public function __construct(DbCli $dbService, ConnectedUserService $connectedUserService)
    {
        parent::__construct($dbService, $connectedUserService);

        $this->entityClass = RolfRisk::class;
    }

    public function findByAnrAndCode(Anr $anr, string $code): ?RolfRisk
    {
        return $this->getRepository()
            ->createQueryBuilder('rr')
            ->where('rr.anr = :anr')
            ->andWhere('rr.code = :code')
            ->setParameter('anr', $anr)
            ->setParameter('code', $code)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
